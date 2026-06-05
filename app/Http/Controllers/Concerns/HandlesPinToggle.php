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
        return $request->has('use_pin') && 
               ($request->input('use_pin') === '1' || 
                $request->input('use_pin') === 'on' || 
                $request->boolean('use_pin'));
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
        if ($usePin) {
            if (!empty($pin)) {
                // Update PIN if provided and different
                $currentPin = $user->getViewPin();
                if ($pin !== $currentPin) {
                    $user->setViewPin($pin);
                }
            } else {
                // Generate PIN if toggle is on but no PIN provided and user has no stored PIN
                if (!$user->hasStoredPin()) {
                    $user->generateViewPin();
                }
                // Otherwise, keep existing PIN (preserves PIN when toggle is re-enabled)
            }
        } else {
            // Toggle is OFF - clear the PIN to disable it
            if ($user->hasStoredPin()) {
                $user->update(['view_pin' => null]);
            }
        }
    }
}

