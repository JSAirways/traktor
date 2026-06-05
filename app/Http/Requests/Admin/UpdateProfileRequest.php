<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesPinValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    use HandlesPinValidation;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize email to lowercase before validation (emails are case-insensitive)
        if ($this->has('email') && $this->input('email')) {
            $this->merge(['email' => strtolower($this->input('email'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $user = auth()->user();
        $usePin = $this->getPinToggleState();
        
        $rules = [
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'cat_gif' => ['nullable', 'string', 'max:255'],
            'appears_in_profile_selection' => ['nullable', 'boolean'],
            'use_pin' => ['nullable'],
        ];

        return $this->addPinValidationRules($rules, $usePin);
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => __('messages.email_required'),
            'email.email' => __('messages.email_invalid'),
            'email.unique' => __('messages.email_taken'),
            'username.required' => __('messages.username_required'),
            'password.min' => __('messages.password_min'),
            'pin.required' => __('messages.pin_required'),
            'pin.size' => __('messages.pin_size'),
            'pin.regex' => __('messages.pin_format'),
        ];
    }
}

