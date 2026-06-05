<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    public function __construct(
        protected DeviceRegistrationService $deviceService
    ) {
    }

    public function getLoginFormData(Request $request): array
    {
        $device = $this->deviceService->getDeviceFromCookie($request);
        $parentUserId = null;
        $parentEmail = null;

        if ($device && $device->isActive()) {
            if (!$device->relationLoaded('parent')) {
                $device->load('parent');
            }

            if ($device->parent) {
                $parentUserId = $device->parent->id;
                $parentEmail = $device->parent->email;
            }
        }

        return [
            'deviceRegistered' => $device !== null && $device->isActive(),
            'parentUserId' => $parentUserId,
            'parentEmail' => $parentEmail,
        ];
    }

    public function attemptLogin(Request $request, bool $remember = false, bool $alreadyAuthenticated = false): array
    {
        if ($alreadyAuthenticated && Auth::check()) {
            return $this->validateAccountStatus(Auth::user());
        }

        $device = $this->deviceService->getDeviceFromCookie($request);

        if ($device && $device->isActive()) {
            return $this->attemptDeviceLogin($device, $request->password, $remember);
        }

        return $this->attemptNormalLogin(
            $request->email,
            $request->password,
            $remember
        );
    }

    protected function attemptDeviceLogin($device, string $password, bool $remember): array
    {
        if (!$device->relationLoaded('parent') || !$device->parent) {
            $device->load('parent');
        }

        if (!$device->parent) {
            throw ValidationException::withMessages([
                'password' => __('auth.failed'),
            ]);
        }

        $credentials = [
            'email' => $device->parent->email,
            'password' => $password,
        ];

        if (!Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'password' => __('auth.failed'),
            ]);
        }

        return $this->validateAccountStatus(Auth::user());
    }

    protected function attemptNormalLogin(string $email, string $password, bool $remember): array
    {
        $credentials = [
            'email' => $email,
            'password' => $password,
        ];

        if (!Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $this->validateAccountStatus(Auth::user());
    }

    public function validateAccountStatus(User $user): array
    {
        if ($user->isPending()) {
            Auth::logout();
            return [false, $user, 'pending-approval'];
        }

        if ($user->isRejected()) {
            Auth::logout();
            return [false, $user, 'account-rejected'];
        }

        if (!$user->isApproved()) {
            Auth::logout();
            return [false, $user, 'pending-approval'];
        }

        return [true, $user, null];
    }

    public function logout(Request $request): string
    {
        Auth::guard('web')->logout();

        $request->session()->regenerate(true);

        if ($redirect = $request->input('redirect')) {
            return $redirect;
        }

        return route('welcome');
    }
}


