<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Check if device is registered (password-only login)
        $deviceService = app(\App\Services\DeviceRegistrationService::class);
        $device = $deviceService->getDeviceFromCookie($this);
        
        $rules = [
            'password' => ['required', 'string'],
        ];

        // Email is only required if device is not registered
        if (!$device || !$device->isActive()) {
            $rules['email'] = ['required', 'string', 'email'];
        }

        return $rules;
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => $this->string('email'),
            'password' => $this->string('password'),
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     * Skip rate limiting for admin users.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        // Check if user is admin - skip rate limiting for admins
        $email = $this->string('email', '');
        if ($email) {
            $user = \App\Models\User::where('email', $email)->first();
            if ($user && $user->isAdmin()) {
                // Admin user - skip rate limiting
                return;
            }
        }

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $email = $this->string('email', '');
        return Str::transliterate(Str::lower($email).'|'.$this->ip());
    }
}
