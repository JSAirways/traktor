@props(['text' => null, 'size' => '3rem'])

@php
    $displayText = $text ?? __('common.loading');
    $defaultText = __('common.loading');
@endphp

<div class="text-center">
    <div class="spinner-border text-success" role="status" style="width: {{ $size }}; height: {{ $size }};">
        <span class="visually-hidden">{{ $displayText }}</span>
    </div>
    @if($displayText !== $defaultText)
        <p class="mt-3 text-muted">{{ $displayText }}</p>
    @endif
</div>

