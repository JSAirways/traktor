<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertChildToParentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->route('user');
        // Only allow conversion if user is a child account
        return auth()->check() 
            && auth()->user()->isAdmin() 
            && $user 
            && $user->parent_id !== null;
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
        $user = $this->route('user');
        
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user ? $user->id : null),
            ],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'string', 'same:password'],
        ];
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
            'password.required' => __('messages.password_required'),
            'password.min' => __('messages.password_min'),
            'password_confirmation.required' => __('messages.password_confirmation_required'),
            'password_confirmation.same' => __('messages.password_confirmation_mismatch'),
        ];
    }
}
