@props([
    'name',
    'label',
    'type' => 'text',
    'required' => false,
    'value' => null,
    'placeholder' => null,
    'error' => null,
    'autofocus' => false,
    'clearValue' => false,
    'maxlength' => null,
])

@php
    // Never prefill password fields for security reasons
    // Also, if value is explicitly set to empty string or null, don't use old()
    if ($type === 'password') {
        $inputValue = null;
        $autocomplete = 'new-password';
    } elseif ($value !== null && $value !== '') {
        // Only use value if it's not empty
        $inputValue = $value;
        $autocomplete = 'off';
    } elseif ($value === '') {
        // Explicitly empty string - don't use old()
        $inputValue = null;
        $autocomplete = 'off';
    } else {
        $inputValue = old($name);
        $autocomplete = $type === 'email' ? 'email' : ($type === 'password' ? 'new-password' : 'off');
    }
    $displayLabel = $label . ($required ? ' *' : '');
@endphp

<div class="form-floating mb-3">
    <input 
        type="{{ $type }}" 
        class="form-control @error($name) is-invalid @enderror" 
        id="{{ $name }}" 
        name="{{ $name }}" 
        placeholder=" "
        autocomplete="{{ $autocomplete }}"
        @if($inputValue && $inputValue !== '') value="{{ $inputValue }}" @endif
        @if($required) required @endif
        @if($autofocus) autofocus @endif
        @if($maxlength) maxlength="{{ $maxlength }}" @endif
    >
    <label for="{{ $name }}">
        {{ $displayLabel }}
    </label>
    {{-- Always render invalid-feedback element for AJAX validation support --}}
    {{-- Hidden by default, shown when there's an error --}}
    <div class="invalid-feedback @if(!$errors->has($name) && !$error) d-none @endif" id="{{ $name }}_error">
        @error($name)
            {{ $message }}
        @else
            @if($error && !$errors->has($name))
                {{ $error }}
            @endif
        @enderror
    </div>
</div>
