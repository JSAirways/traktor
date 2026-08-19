@props([
    'inputId' => 'pinEntryPin',
    'inputName' => 'pin',
    'maxlength' => 4,
    'pattern' => '[0-9]{4}',
    'errorId' => 'pinEntryError',
    'loadingId' => 'pinEntryLoading',
    'placeholder' => null,
    'autofocus' => false,
    'autocomplete' => 'off',
])

<div class="mb-3">
    <input type="text"
           class="form-control @error($inputName) is-invalid @enderror"
           id="{{ $inputId }}"
           name="{{ $inputName }}"
           maxlength="{{ $maxlength }}"
           pattern="{{ $pattern }}"
           inputmode="numeric"
           autocomplete="{{ $autocomplete }}"
           @if($placeholder !== null) placeholder="{{ $placeholder }}" @endif
           required
           @if($autofocus) autofocus @endif>
</div>
<div id="{{ $errorId }}" class="invalid-feedback d-none mb-3"></div>
<div id="{{ $loadingId }}" class="text-center mt-3" style="display: none;">
    <div class="spinner-border text-success" role="status">
        <span class="visually-hidden">Validating...</span>
    </div>
</div>
