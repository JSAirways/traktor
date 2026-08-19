@props([
    'user' => null, // User model instance (optional)
    'currentPin' => null, // Current PIN value (decrypted)
    'pinEnabled' => null, // Whether PIN is enabled (optional, will use user->hasPin() if user provided)
    'label' => 'PIN',
    'helpText' => null,
    'pinName' => 'pin',
    'toggleName' => 'use_pin',
    'pinGetter' => 'getViewPin',
    'pinEnabledGetter' => 'hasPin',
    'fieldId' => 'pin', // Input field ID
    'wrapperId' => 'pin-field-wrapper', // Wrapper div ID
    'asteriskId' => 'pin-asterisk', // Asterisk span ID
    'checkboxId' => 'use_pin', // Toggle checkbox ID / element id
    'columnClasses' => 'col-12 col-sm-auto mb-3 mb-sm-0', // Column classes for grid layout (set to empty string to disable)
])

@php
    // Get current PIN if not provided and user is available
    if ($currentPin === null && $user && method_exists($user, $pinGetter)) {
        $currentPin = $user->{$pinGetter}();
    }
    // Check if PIN is enabled (use provided value, or user->hasPin() if user provided, or default to false)
    if ($pinEnabled === null && $user && method_exists($user, $pinEnabledGetter)) {
        $pinEnabled = $user->{$pinEnabledGetter}();
    } elseif ($pinEnabled === null) {
        $pinEnabled = false;
    }
    $oldToggleValue = old($toggleName, $pinEnabled);
@endphp

<div @if($columnClasses) class="{{ $columnClasses }}" @endif>
    <label class="form-label fw-bold d-flex align-items-center justify-content-between">
        <span>{{ $label }} <span id="{{ $asteriskId }}" class="text-danger" style="display: {{ $oldToggleValue ? 'inline' : 'none' }};">*</span></span>
        <div class="form-check form-switch ms-2">
            <input class="form-check-input" 
                   type="checkbox" 
                   id="{{ $checkboxId }}" 
                   name="{{ $toggleName }}" 
                   {{ $oldToggleValue ? 'checked' : '' }}
                   data-pin-toggle="{{ json_encode([
                       'pinWrapperId' => $wrapperId,
                       'pinInputId' => $fieldId,
                       'pinAsteriskId' => $asteriskId,
                       'usePinCheckboxId' => $checkboxId,
                       'currentPin' => $currentPin ?? '',
                       'pinName' => $pinName
                   ]) }}">
        </div>
    </label>
    <div id="{{ $wrapperId }}">
        <div class="input-group">
            <input type="text" class="form-control @error($pinName) is-invalid @enderror" id="{{ $fieldId }}" name="{{ $pinName }}" value="{{ old($pinName, $currentPin ?? '') }}" maxlength="4" pattern="[0-9]{4}" placeholder="{{ __('forms.enter_pin') }}" {{ $pinEnabled ? 'required' : '' }}>
            <button type="button" 
                    class="btn bg-success text-white" 
                    data-generate-pin="{{ json_encode([
                        'pinInputId' => $fieldId,
                        'usePinCheckboxId' => $checkboxId,
                        'pinName' => $pinName
                    ]) }}"
                    title="{{ __('admin.generate_pin_title') }}">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>
    @if($helpText)
        <div class="form-text">{{ $helpText }}</div>
    @endif
    @error($pinName)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

