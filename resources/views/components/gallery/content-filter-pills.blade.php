@props([
    'selectedType' => 'all', // 'all'|'videos'|'playlists'
    'elementId' => 'contentFilterPills', // Custom ID for the container (allows multiple instances)
])

@php
    // Generate unique button IDs based on container ID to avoid duplicate IDs
    $videosBtnId = $elementId === 'contentFilterPillsLandscape' ? 'filterVideosBtnLandscape' : 'filterVideosBtn';
    $playlistsBtnId = $elementId === 'contentFilterPillsLandscape' ? 'filterPlaylistsBtnLandscape' : 'filterPlaylistsBtn';
@endphp

{{--
    Content Filter Pills Component
    
    Filter pills for switching between Videos and Playlists view.
    Uses Bootstrap nav-pills with bg-light (inactive) and bg-success (active) styling.
    
    @prop string $selectedType - Currently selected filter type: 'all'|'videos'|'playlists'
    @prop string $elementId - Custom ID for the container (default: 'contentFilterPills')
--}}

<ul class="nav nav-pills d-flex" id="{{ $elementId }}" role="tablist">
    <li class="nav-item" role="presentation">
        <button 
            class="nav-link {{ $selectedType === 'videos' ? 'bg-success text-light' : 'bg-light text-dark' }}" 
            id="{{ $videosBtnId }}"
            type="button"
            data-content-type="videos"
            role="tab"
            aria-selected="{{ $selectedType === 'videos' ? 'true' : 'false' }}"
            aria-label="{{ __('gallery.filter_videos') }}"
            title="{{ __('gallery.filter_videos') }}">
            <i class="bi bi-play-btn fs-5"></i>
            <span class="d-none d-md-inline ms-2">{{ __('gallery.videos') }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button 
            class="nav-link {{ $selectedType === 'playlists' ? 'bg-success text-light' : 'bg-light text-dark' }}" 
            id="{{ $playlistsBtnId }}"
            type="button"
            data-content-type="playlists"
            role="tab"
            aria-selected="{{ $selectedType === 'playlists' ? 'true' : 'false' }}"
            aria-label="{{ __('gallery.filter_playlists') }}"
            title="{{ __('gallery.filter_playlists') }}">
            <i class="bi bi-collection-play fs-5"></i>
            <span class="d-none d-md-inline ms-2">{{ __('gallery.playlists') }}</span>
        </button>
    </li>
</ul>

