@props([
    'title',
    'titleId' => null,
])

{{--
    Uniform admin page header:
    1) Page title
    2) Optional controls row (selectors, actions, save, back, etc.)
    3) Page content follows outside this component
--}}
<div {{ $attributes->merge(['class' => 'admin-page-header mb-3 mb-md-4']) }}>
    <div class="admin-page-header__title mb-2 mb-md-3">
        <h2 @if($titleId) id="{{ $titleId }}" @endif class="mb-0">{{ $title }}</h2>
        @isset($subtitle)
            <div class="admin-page-header__subtitle mt-1">{{ $subtitle }}</div>
        @endisset
    </div>

    @isset($controls)
        <div class="admin-page-header__controls d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-2">
            {{ $controls }}
        </div>
    @endisset
</div>
