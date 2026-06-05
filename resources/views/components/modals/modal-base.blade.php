@props([
    'id' => null,              // Modal ID (required)
    'showHeader' => false,     // Whether to show modal header
    'title' => null,           // Header title (if showHeader is true)
    'titleId' => null,         // Title ID for accessibility
    'closeButton' => true,     // Whether to show close button in header
])

@php
    // Generate title ID if not provided
    $titleId = $titleId ?? ($id ? $id . 'Label' : null);
@endphp

<!-- Base Modal Structure -->
<div class="modal fade" id="{{ $id }}" tabindex="-1" 
     @if($titleId) aria-labelledby="{{ $titleId }}" @endif
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-transparent border-0">
            @if($showHeader)
                <div class="modal-header border-0">
                    @if($title)
                        <h5 class="modal-title text-light" id="{{ $titleId }}">{{ $title }}</h5>
                    @endif
                    @if($closeButton)
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    @endif
                </div>
            @endif
            <div class="modal-body">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>

