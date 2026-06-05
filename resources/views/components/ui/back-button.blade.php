@props(['href', 'text' => null, 'icon' => 'bi-chevron-left'])

@php
    $displayText = $text ?? __('common.back');
@endphp

<a href="{{ $href }}" class="btn btn-outline-light">
    <i class="bi {{ $icon }} me-1"></i>{{ $displayText }}
</a>

