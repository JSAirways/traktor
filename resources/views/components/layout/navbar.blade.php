@props([
    'variant' => 'frontend', // 'admin' | 'frontend' | 'gallery'
    'brandText' => null, // Optional text after icon (default: "Traktor" for frontend, "Admin Panel" for admin, username for gallery)
    'showSidebarToggle' => false, // Admin only
    'containerClass' => 'container-fluid px-2 px-md-3', // Container classes
    'playerViewMode' => false, // Gallery: apply player-view-mode class for fade behavior
    'username' => null, // Gallery: username for branding
])

@php
    $isAdmin = $variant === 'admin';
    $isGallery = $variant === 'gallery';
    $defaultBrandText = $brandText ?? ($isAdmin ? 'Admin Panel' : ($isGallery && $username ? $username . "'s" : 'Traktor'));
    $navbarClass = 'navbar navbar-dark bg-dark navbar-expand-md';
    if ($isGallery) {
        $navbarClass .= ' top-navbar' . ($playerViewMode ? ' player-view-mode' : '');
    }
@endphp

<nav class="{{ $navbarClass }}">
    <div class="{{ $containerClass }}">
        @if($showSidebarToggle)
            <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
        @endif
        <span class="navbar-brand d-flex align-items-center">
            @if($isGallery)
                <h2 class="m-0 d-flex align-items-center">
                    <span class="text-truncate d-none d-sm-inline me-2" style="max-width: 200px;">{{ $defaultBrandText }}</span>
                    <img src="{{ asset('tractor.png') }}" width="40" height="40" alt="{{ __('common.app_name') }}" class="flex-shrink-0"/>
                </h2>
            @else
                <h2 class="m-0 d-none d-sm-inline me-2">{{ $defaultBrandText }}</h2>
                <img src="{{ asset('tractor.png') }}" width="40" height="40" alt="{{ __('common.app_name') }}"/>
            @endif
        </span>
        @if($isGallery)
            <div class="d-flex align-items-center flex-grow-1">
                <div class="flex-grow-1 text-center">
                    {{ $center ?? '' }}
                </div>
                <div class="d-flex align-items-center">
                    {{ $actions ?? '' }}
                </div>
            </div>
        @else
            <div class="d-flex align-items-center">
                {{ $actions ?? '' }}
            </div>
        @endif
    </div>
</nav>

