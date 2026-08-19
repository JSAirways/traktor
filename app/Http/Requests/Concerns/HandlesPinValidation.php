<?php

namespace App\Http\Requests\Concerns;

trait HandlesPinValidation
{
    /**
     * Get PIN toggle state from request.
     */
    protected function getPinToggleState(): bool
    {
        return $this->getNamedPinToggleState('use_pin');
    }

    /**
     * Get admin PIN toggle state from request.
     */
    protected function getAdminPinToggleState(): bool
    {
        return $this->getNamedPinToggleState('use_admin_pin');
    }

    /**
     * Get toggle state for a named PIN checkbox field.
     */
    protected function getNamedPinToggleState(string $toggleField): bool
    {
        return $this->has($toggleField) &&
            ($this->input($toggleField) === '1' ||
             $this->input($toggleField) === 'on' ||
             $this->boolean($toggleField));
    }

    /**
     * Add PIN validation rules if toggle is enabled.
     */
    protected function addPinValidationRules(array $rules, bool $usePin): array
    {
        return $this->addNamedPinValidationRules($rules, $usePin, 'use_pin', 'pin');
    }

    /**
     * Add validation rules for a configurable PIN toggle / field pair.
     */
    protected function addNamedPinValidationRules(
        array $rules,
        bool $usePin,
        string $toggleField = 'use_pin',
        string $pinField = 'pin'
    ): array {
        $rules[$toggleField] = 'nullable';

        if ($usePin) {
            $rules[$pinField] = ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/'];
        }

        return $rules;
    }
}

