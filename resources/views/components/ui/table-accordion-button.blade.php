@props([
    'targetId',           // ID for the collapse target (required)
    'mobileTargetId',     // ID for mobile collapse target (optional, defaults to {targetId}-mobile)
    'ariaControls',       // aria-controls value (optional, auto-generated if not provided)
])

@php
    // Ensure targetId is provided
    if (empty($targetId)) {
        \Log::error('table-accordion-button component called without targetId');
        return; // Silently fail instead of throwing exception
    }
    
    $mobileTarget = $mobileTargetId ?? $targetId . '-mobile';
    $controls = $ariaControls ?? "{$targetId} {$mobileTarget}";
    $targets = "#{$targetId}, #{$mobileTarget}";
@endphp

<button 
    type="button"
    class="accordion-button collapsed p-0 border-0 bg-transparent shadow-none"
    data-bs-toggle="collapse"
    data-bs-target="{{ $targets }}"
    aria-expanded="false"
    aria-controls="{{ $controls }}"
>
    <i class="bi bi-chevron-right"></i>
    <i class="bi bi-chevron-down"></i>
</button>

