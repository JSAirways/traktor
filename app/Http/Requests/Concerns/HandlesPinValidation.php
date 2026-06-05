<?php

namespace App\Http\Requests\Concerns;

trait HandlesPinValidation
{
    /**
     * Get PIN toggle state from request.
     */
    protected function getPinToggleState(): bool
    {
        return $this->has('use_pin') && 
               ($this->input('use_pin') === '1' || 
                $this->input('use_pin') === 'on' || 
                $this->boolean('use_pin'));
    }

    /**
     * Add PIN validation rules if toggle is enabled.
     */
    protected function addPinValidationRules(array $rules, bool $usePin): array
    {
        $rules['use_pin'] = 'nullable';
        
        if ($usePin) {
            $rules['pin'] = ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/'];
        }
        
        return $rules;
    }
}

