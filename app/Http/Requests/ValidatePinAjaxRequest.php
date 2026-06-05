<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidatePinAjaxRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // PIN validation is public
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'pin' => ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
            'intended_url' => ['nullable', 'string', 'regex:/^(\/|https?:\/\/)/'], // Allow relative paths (starting with /) or full URLs
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'username.required' => __('messages.username_required'),
            'pin.required' => __('messages.pin_required'),
            'pin.size' => __('messages.pin_size'),
            'pin.regex' => __('messages.pin_format'),
        ];
    }
}

