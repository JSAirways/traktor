<?php

namespace App\Http\Controllers\Concerns;

trait HandlesPinToggle
{
    /**
     * Get PIN toggle state from request.
     * Checkbox sends '1' or 'on' when checked, nothing when unchecked.
     */
    protected function getPinToggleState($request): bool
    {
        return $this->getNamedPinToggleState($request, 'use_pin');
    }

    /**
     * Get admin PIN toggle state from request.
     */
    protected function getAdminPinToggleState($request): bool
    {
        return $this->getNamedPinToggleState($request, 'use_admin_pin');
    }

    /**
     * Get toggle state for a named PIN checkbox field.
     */
    protected function getNamedPinToggleState($request, string $toggleField): bool
    {
        return $request->has($toggleField) &&
            ($request->input($toggleField) === '1' ||
             $request->input($toggleField) === 'on' ||
             $request->boolean($toggleField));
    }

    /**
     * Add PIN validation rules if toggle is enabled.
     * 
     * @param array $rules Existing validation rules
     * @param bool $usePin Whether PIN toggle is enabled
     * @return array Updated validation rules
     */
    protected function addPinValidationRules(array $rules, bool $usePin): array
    {
        $rules['use_pin'] = 'nullable';
        
        if ($usePin) {
            // PIN is required when toggle is on
            // Simplified validation for low-security use case (preventing kids from wrong profiles)
            $rules['pin'] = ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/'];
        }
        
        return $rules;
    }

    /**
     * Handle PIN update based on toggle state.
     * 
     * @param \App\Models\User $user The user to update
     * @param bool $usePin Whether PIN toggle is enabled
     * @param string|null $pin The PIN value (if provided)
     */
    protected function handlePinUpdate($user, bool $usePin, ?string $pin = null): void
    {
        $this->handleNamedPinUpdate($user, $usePin, $pin, 'view_pin');
    }

    /**
     * Handle an arbitrary PIN column update based on toggle state.
     *
     * @param \App\Models\User $user The user to update
     * @param bool $usePin Whether PIN toggle is enabled
     * @param string|null $pin The PIN value (if provided)
     * @param string $pinColumn The encrypted PIN column on users
     */
    protected function handleNamedPinUpdate($user, bool $usePin, ?string $pin = null, string $pinColumn = 'view_pin'): void
    {
        $getter = $pinColumn === 'admin_pin' ? 'getAdminPin' : 'getViewPin';
        $setter = $pinColumn === 'admin_pin' ? 'setAdminPin' : 'setViewPin';
        $hasPinGetter = $pinColumn === 'admin_pin' ? 'hasAdminPin' : 'hasStoredPin';

        if ($usePin) {
            if (!empty($pin)) {
                // Update PIN if provided and different
                $currentPin = $user->{$getter}();
                if ($pin !== $currentPin) {
                    $user->{$setter}($pin);
                }
            } elseif (! $user->{$hasPinGetter}()) {
                // Toggle enabled without a stored PIN and no new value submitted — leave disabled.
            }
        } else {
            // Toggle is OFF - clear the PIN to disable it
            if ($user->{$hasPinGetter}()) {
                $user->update([$pinColumn => null]);
            }
        }
    }
}

