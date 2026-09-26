@props([
    'name' => 'cat_gif',
    'currentValue' => '',
    'pictures' => [],
    'category' => 'cats',
    'compact' => false, // When true, hide label (for avatar+profile name row layouts)
])

@php
    $currentValue = $currentValue ?? '';
    $selectedValue = old($name, $currentValue);
    $modalId = 'profile-picture-modal-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $name);
    $triggerId = 'profile-picture-trigger-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $name);
    $assetBase = asset('assets/profile-pictures/' . $category);
    $hasImage = $selectedValue !== '' && $selectedValue !== null;
    $pickerConfig = json_encode([
        'hiddenInputId' => $name,
        'modalId' => $modalId,
        'triggerId' => $triggerId,
        'assetBase' => $assetBase,
    ], JSON_HEX_APOS | JSON_HEX_QUOT);
@endphp

<div class="{{ $compact ? '' : 'mb-3' }} profile-picture-picker" data-profile-picture-picker="{{ $pickerConfig }}">
    @unless($compact)
        <label class="form-label fw-bold">{{ __('common.profile_picture') }} <span class="opacity-75">({{ __('common.optional') }})</span></label>
    @endunless
    <input type="hidden" name="{{ $name }}" id="{{ $name }}" value="{{ $selectedValue }}">
    @error($name)
        <div class="text-danger small mb-2">{{ $message }}</div>
    @enderror

    <button type="button"
            class="profile-picture-trigger btn p-0 border-0 bg-transparent position-relative"
            id="{{ $triggerId }}"
            data-bs-toggle="modal"
            data-bs-target="#{{ $modalId }}"
            aria-label="{{ __('common.profile_picture') }}">
        <div class="profile-picture-trigger-preview border border-2 border-success rounded-circle overflow-hidden d-flex align-items-center justify-content-center">
            <img src="{{ $hasImage ? $assetBase . '/' . $selectedValue : '' }}"
                 alt=""
                 class="profile-picture-trigger-img w-100 h-100 {{ $hasImage ? '' : 'd-none' }}"
                 style="object-fit: contain;">
            <i class="bi bi-shuffle fs-2 text-success profile-picture-trigger-placeholder {{ $hasImage ? 'd-none' : '' }}"></i>
        </div>
        <span class="profile-picture-trigger-edit" aria-hidden="true">
            <i class="bi bi-pencil"></i>
        </span>
    </button>

    <div class="modal fade profile-picture-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content profile-picture-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $modalId }}-label">{{ __('common.profile_picture') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="cat-gif-selector-container">
                        <div class="cat-gif-selector" data-cat-gif-selector="{{ $pickerConfig }}">
                            <div class="cat-gif-option {{ $selectedValue === '' ? 'selected' : '' }}" data-gif-value="">
                                <div class="cat-gif-preview border rounded overflow-hidden d-flex align-items-center justify-content-center">
                                    <i class="bi bi-shuffle fs-1 text-success"></i>
                                </div>
                            </div>
                            @foreach($pictures as $picture)
                                <div class="cat-gif-option {{ $selectedValue === $picture ? 'selected' : '' }}" data-gif-value="{{ $picture }}">
                                    <div class="cat-gif-preview border rounded overflow-hidden">
                                        <img src="{{ $assetBase }}/{{ $picture }}" alt="{{ $picture }}" class="w-100 h-100" style="object-fit: contain;">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
