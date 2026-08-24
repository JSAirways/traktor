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
    <div class="row g-3 align-items-start">
        <div class="col-12 col-lg-8">
            <div class="d-flex align-items-start justify-content-between gap-3">
                <div>
                    <label class="form-label fw-bold mb-1" for="{{ $checkboxId }}">
                        {{ $label }} <span id="{{ $asteriskId }}" class="text-danger" style="display: {{ $oldToggleValue ? 'inline' : 'none' }};">*</span>
                    </label>
                    @if($helpText)
                        <div class="form-text mt-0">{{ $helpText }}</div>
                    @endif
                </div>

                <div class="form-check form-switch mt-1">
                    <input class="form-check-input"
                           type="checkbox"
                           role="switch"
                           id="{{ $checkboxId }}"
                           name="{{ $toggleName }}"
                           value="1"
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
            </div>
        </div>

        <div id="{{ $wrapperId }}" class="col-12 col-lg-4" @if(!$oldToggleValue) style="display: none;" @endif>
            <input type="text"
                   class="form-control @error($pinName) is-invalid @enderror"
                   id="{{ $fieldId }}"
                   @if($oldToggleValue) name="{{ $pinName }}" @endif
                   value="{{ old($pinName, $currentPin ?? '') }}"
                   maxlength="4"
                   pattern="[0-9]{4}"
                   inputmode="numeric"
                   autocomplete="off"
                   placeholder="{{ __('forms.enter_pin') }}"
                   {{ $oldToggleValue ? 'required' : '' }}>
            <button type="button"
                    class="btn btn-outline-success btn-sm mt-2"
                    data-generate-pin="{{ json_encode([
                        'pinInputId' => $fieldId,
                        'usePinCheckboxId' => $checkboxId,
                        'pinName' => $pinName
                    ]) }}"
                    title="{{ __('admin.generate_pin_title') }}">
                <i class="bi bi-arrow-clockwise me-1"></i>{{ __('admin.generate_pin') }}
            </button>
            @error($pinName)
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

