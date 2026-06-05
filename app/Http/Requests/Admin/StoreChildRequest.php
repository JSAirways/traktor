<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesPinValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChildRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $usePin = $this->getPinToggleState();
        $parentId = auth()->id();
        
        $rules = [
            'username' => [
                'required', 
                'string', 
                'max:255',
                // Ensure username is unique per parent (composite unique constraint)
                Rule::unique('users', 'username')->where(function ($query) use ($parentId) {
                    return $query->where('parent_id', $parentId);
                }),
            ],
            'cat_gif' => ['nullable', 'string', 'max:255'],
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
            'username.required' => __('messages.username_required'),
            'username.max' => __('messages.username_max'),
            'pin.required' => __('messages.pin_required'),
            'pin.size' => __('messages.pin_size'),
            'pin.regex' => __('messages.pin_format'),
        ];
    }
}

