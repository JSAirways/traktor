@props([
    'name' => 'cat_gif',
    'currentValue' => '',
    'pictures' => [],
    'category' => 'cats'
])

@php
    // Ensure currentValue is a string
    $currentValue = $currentValue ?? '';
@endphp

<div class="mb-3">
    <label class="form-label fw-bold">{{ __('common.profile_picture') }} <span class="text-muted">({{ __('common.optional') }})</span></label>
    <input type="hidden" name="{{ $name }}" id="{{ $name }}" value="{{ old($name, $currentValue) }}">
    @error($name)
        <div class="text-danger small mb-2">{{ $message }}</div>
    @enderror
    <div class="cat-gif-selector-container">
        <div class="cat-gif-selector row g-2 g-md-3" data-cat-gif-selector='{"hiddenInputId": "{{ $name }}"}'>
            <div class="col-4 col-sm-3 col-lg-auto">
                <div class="cat-gif-option {{ (old($name, $currentValue)) === '' ? 'selected' : '' }}" data-gif-value="">
                    <div class="cat-gif-preview border rounded overflow-hidden p-3 d-flex align-items-center justify-content-center">
                        <i class="bi bi-shuffle fs-1 text-success"></i>
                    </div>
                </div>
            </div>
            @foreach($pictures as $picture)
                <div class="col-4 col-sm-3 col-lg-auto">
                    <div class="cat-gif-option {{ (old($name, $currentValue)) === $picture ? 'selected' : '' }}" data-gif-value="{{ $picture }}">
                        <div class="cat-gif-preview border rounded overflow-hidden p-3">
                            <img src="{{ asset('assets/profile-pictures/' . $category . '/' . $picture) }}" alt="{{ $picture }}" class="w-100 h-100" style="object-fit: contain;">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

