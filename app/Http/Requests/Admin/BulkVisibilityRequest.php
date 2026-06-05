<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkVisibilityRequest extends FormRequest
{
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
        return [
            'items' => ['required', 'string'], // JSON string
            'visible' => ['required', 'boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure items is a valid JSON string
        if ($this->has('items') && !is_string($this->input('items'))) {
            $this->merge([
                'items' => json_encode($this->input('items')),
            ]);
        }
    }
}

