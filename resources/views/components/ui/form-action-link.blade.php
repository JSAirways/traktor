@props([
    'href' => null, // Route name or URL
    'icon' => null, // Bootstrap icon class
    'variant' => 'outline-success', // Button variant
    'title' => null,
])

<a href="{{ $href }}" class="btn btn-{{ $variant }} border-0" title="{{ $title }}">
    @if($icon)
        <i class="{{ $icon }}"></i>
    @endif
    {{ $slot }}
</a>



