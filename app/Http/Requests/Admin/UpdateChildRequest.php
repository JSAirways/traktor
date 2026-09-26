<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesPinValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChildRequest extends FormRequest
{
    use HandlesPinValidation;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $child = $this->route('child');
        $user = auth()->user();
        
        return $user && $child && $child->parent_id === $user->id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $usePin = $this->getPinToggleState();
        $child = $this->route('child');
        $parentId = $child ? $child->parent_id : auth()->id();
        
        $rules = [
            'profile_name' => [
                'required', 
                'string', 
                'max:255',
                // Ensure profile_name is unique per parent, excluding current child
                Rule::unique('users', 'profile_name')
                    ->where(function ($query) use ($parentId) {
                        return $query->where('parent_id', $parentId);
                    })
                    ->ignore($child ? $child->id : null),
            ],
            'cat_gif' => ['nullable', 'string', 'max:255'],
            'use_pin' => ['nullable'],
            'pause_overlay_enabled' => ['nullable', 'boolean'],
            'device_visibility' => ['nullable', 'array'],
            'device_visibility.*' => ['nullable'],
        ];

        return $this->addPinValidationRules($rules, $usePin);
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'profile_name.required' => __('messages.profile_name_required'),
            'profile_name.max' => __('messages.profile_name_max'),
            'pin.required' => __('messages.pin_required'),
            'pin.size' => __('messages.pin_size'),
            'pin.regex' => __('messages.pin_format'),
        ];
    }
}

