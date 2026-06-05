@props([
    'user' => null, // User model instance (optional)
    'currentPin' => null, // Current PIN value (decrypted)
    'pinEnabled' => null, // Whether PIN is enabled (optional, will use user->hasPin() if user provided)
    'fieldId' => 'pin', // Input field ID
    'wrapperId' => 'pin-field-wrapper', // Wrapper div ID
    'asteriskId' => 'pin-asterisk', // Asterisk span ID
    'checkboxId' => 'use_pin', // Toggle checkbox ID
    'columnClasses' => 'col-12 col-sm-auto mb-3 mb-sm-0', // Column classes for grid layout (set to empty string to disable)
])

@php
    // Get current PIN if not provided and user is available
    if ($currentPin === null && $user) {
        $currentPin = $user->getViewPin();
    }
    // Check if PIN is enabled (use provided value, or user->hasPin() if user provided, or default to false)
    if ($pinEnabled === null && $user) {
        $pinEnabled = $user->hasPin();
    } elseif ($pinEnabled === null) {
        $pinEnabled = false;
    }
@endphp

<div @if($columnClasses) class="{{ $columnClasses }}" @endif>
    <label class="form-label fw-bold d-flex align-items-center justify-content-between">
        <span>PIN <span id="{{ $asteriskId }}" class="text-danger" style="display: {{ old('use_pin', $pinEnabled) ? 'inline' : 'none' }};">*</span></span>
        <div class="form-check form-switch ms-2">
            <input class="form-check-input" 
                   type="checkbox" 
                   id="{{ $checkboxId }}" 
                   name="{{ $checkboxId }}" 
                   {{ old('use_pin', $pinEnabled) ? 'checked' : '' }}
                   data-pin-toggle="{{ json_encode([
                       'pinWrapperId' => $wrapperId,
                       'pinInputId' => $fieldId,
                       'pinAsteriskId' => $asteriskId,
                       'usePinCheckboxId' => $checkboxId,
                       'currentPin' => $currentPin ?? ''
                   ]) }}">
        </div>
    </label>
    <div id="{{ $wrapperId }}">
        <div class="input-group">
            <input type="text" class="form-control @error('pin') is-invalid @enderror" id="{{ $fieldId }}" name="{{ $fieldId }}" value="{{ old('pin', $currentPin ?? '') }}" maxlength="6" pattern="[0-9]{4,6}" placeholder="{{ __('forms.enter_pin') }}" {{ $pinEnabled ? 'required' : '' }}>
            <button type="button" 
                    class="btn bg-success text-white" 
                    data-generate-pin="{{ json_encode([
                        'pinInputId' => $fieldId,
                        'usePinCheckboxId' => $checkboxId
                    ]) }}"
                    title="{{ __('admin.generate_pin_title') }}">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>
    @error('pin')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

