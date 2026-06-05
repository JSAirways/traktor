@props([
    'variant' => 'normal', // 'profile' | 'normal' | 'normal-lg' | 'tile'
    'user' => null,        // User object (for tile variant or when resolving profile picture)
    'image' => null,       // Direct image URL
    'title' => null,       // Title text (for headers)
    'username' => null,    // Username text (for tiles, alternative to title)
    'containerId' => null, // Container ID for JavaScript population
    'icon' => null,        // Icon class (e.g., 'bi-plus') for profile variant without image
    'size' => 'normal',    // Size for tile variant: 'normal' | 'large' | 'small'
    'showName' => true,    // Show name for tile variant
    'mb' => 'mb-4',       // Margin bottom
    'lightTheme' => false, // Set to true for light theme (admin), false for dark theme (default)
    'borderColor' => 'success', // Border color: 'success' (default, green) | 'light' | 'none' or '0' (no border)
])

@php
    $isProfile = $variant === 'profile';
    $isNormalLg = $variant === 'normal-lg';
    $isTile = $variant === 'tile';
    // Escape containerId to prevent XSS - HTML attribute values must be escaped
    $containerIdAttr = $containerId ? 'id="' . e($containerId) . '"' : '';
    
    // Resolve image from user object if provided
    $resolvedImage = $image;
    if ($user && !$resolvedImage) {
        if (isset($user->profile_picture) && $user->profile_picture) {
            $category = $user->profile_picture_category ?? 'cats';
            $resolvedImage = asset('assets/profile-pictures/' . $category . '/' . $user->profile_picture);
        } elseif (isset($user->cat_gif) && $user->cat_gif) {
            // Legacy support - use cats category
            $resolvedImage = asset('assets/profile-pictures/cats/' . $user->cat_gif);
        } else {
            // Get random picture from profile-pictures/cats folder
            $profilePictureService = app(\App\Services\ProfilePictureService::class);
            $randomPicture = $profilePictureService->getRandomPicture('cats');
            if ($randomPicture) {
                $resolvedImage = asset('assets/profile-pictures/cats/' . $randomPicture);
            }
        }
    }
    
    // Resolve username/title from user object if provided
    $resolvedUsername = $username;
    $resolvedTitle = $title;
    if ($user && $isTile) {
        // For tile variant, use username for display
        if (!$resolvedTitle && isset($user->username)) {
            $resolvedTitle = $user->username;
        } elseif (!$resolvedUsername && isset($user->username)) {
            $resolvedUsername = $user->username;
        }
    }
    
    // Determine circle size class for tile variant
    $circleSizeClass = 'user-avatar-circle';
    if ($isTile) {
        if ($size === 'large') {
            $circleSizeClass = 'user-avatar-circle-lg';
        } elseif ($size === 'small') {
            $circleSizeClass = 'user-avatar-circle-sm';
        } else {
            $circleSizeClass = 'user-avatar-circle';
        }
    }
    
    // Determine heading level and title margin
    $headingLevel = $isNormalLg ? 'h3' : 'h5';
    $titleMargin = ($isProfile || $isTile) ? 'mt-2' : 'mt-2';
    
    // Build title classes: always text-center, conditionally text-light based on theme
    $titleClass = 'text-center';
    if (!$lightTheme) {
        $titleClass .= ' text-light';
    }
    if ($isProfile || $isTile) {
        $titleClass .= ' mb-0';
    }
    
    // For tile variant, add hover/pointer styles
    $tileWrapperClass = '';
    if ($isTile) {
        $tileWrapperClass = 'user-avatar-tile';
    }
    
    // For small variant, add navbar-specific class
    $isSmall = $size === 'small';
    
    // Build border class based on borderColor prop
    $borderClass = '';
    if ($borderColor === 'none' || $borderColor === '0') {
        $borderClass = 'border-0';
    } else {
        $borderClass = 'border border-' . $borderColor;
    }
@endphp

<div class="text-center d-flex flex-column align-items-center {{ $mb }} {{ $tileWrapperClass }}" {!! $containerIdAttr !!}>
    @if($isProfile || $isTile)
        {{-- Profile/Tile variant: circular with border --}}
        @if($resolvedImage || $icon || ($isProfile && $containerId))
            {{-- Render circle if image/icon provided, or if profile variant with containerId (for JS population) --}}
            <div class="{{ $circleSizeClass }} bg-dark {{ $borderClass }} text-light d-flex align-items-center justify-content-center overflow-hidden">
                @if($resolvedImage)
                    <img src="{{ $resolvedImage }}" alt="{{ $resolvedTitle ?? $resolvedUsername ?? __('common.profile') }}" class="user-avatar-image {{ $isSmall ? 'user-avatar-image-sm' : '' }}" />
                @elseif($icon)
                    <i class="{{ $icon }} fs-1 text-success"></i>
                @elseif($isProfile && $containerId)
                    {{-- Empty img element for JavaScript to populate - avoids innerHTML usage --}}
                    {{-- Initially hidden, JavaScript will set src and show it --}}
                    <img src="" alt="{{ __('common.profile') }}" class="user-avatar-image {{ $isSmall ? 'user-avatar-image-sm' : '' }} d-none" />
                @endif
            </div>
        @endif
    @else
        {{-- Normal/Normal-lg variant: rectangular image --}}
        @if($resolvedImage)
            <img src="{{ $resolvedImage }}" alt="{{ $resolvedTitle ?? __('common.header_image') }}" 
                 style="@if($isNormalLg) max-width: 250px; max-height: 250px; @else max-width: 150px; max-height: 150px; @endif width: auto; height: auto;">
        @endif
    @endif
    
    @if($isTile && $showName)
        {{-- Tile variant: show name (title) or username as fallback --}}
        @if($resolvedTitle)
            <h5 class="mt-2 mb-0 text-light">{{ $resolvedTitle }}</h5>
        @elseif($resolvedUsername)
            <h5 class="mt-2 mb-0 text-light">{{ $resolvedUsername }}</h5>
        @endif
    @elseif($resolvedTitle && !$isTile)
        {{-- Non-tile variants: show title --}}
        <{{ $headingLevel }} class="{{ $titleClass }} {{ $titleMargin }}">
            {{ $resolvedTitle }}
        </{{ $headingLevel }}>
    @endif
</div>

