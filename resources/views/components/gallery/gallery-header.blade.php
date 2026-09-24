{{--
    Gallery Header Component

    Single header for gallery browse and playlist modes.
    - Browse: channel sidebar toggle (mobile) + content filter pills
    - Playlist: playlist title + back button on one row (title truncates; back aligned right)

    Toggle via #galleryHeader.is-playlist (set by gallery.js).

    @prop string $contentType - Currently selected content type: 'all'|'videos'|'playlists' (default: 'all')
    @prop bool $hasChannels - Whether channels exist (more than just "All Videos") (default: false)
--}}
@props([
    'contentType' => 'all',
    'hasChannels' => false,
])

<div class="gallery-header mb-3" id="galleryHeader" data-layer="gallery-header">
    {{-- Browse mode: channel toggle + filter pills --}}
    <div class="gallery-header-browse d-flex align-items-center gap-2" data-layer="gallery-header-browse">
        @if($hasChannels)
            <button
                class="btn bg-light text-dark channel-sidebar-toggle-pill d-md-none flex-shrink-0"
                data-bs-toggle="offcanvas"
                data-bs-target="#channelSidebarOffcanvas"
                type="button"
                aria-label="{{ __('gallery.channel_sidebar_title') }}"
                title="{{ __('gallery.channel_sidebar_title') }}"
                data-layer="sidebar-toggle">
                <i class="bi bi-compass fs-5"></i>
            </button>
        @endif
        <x-gallery.content-filter-pills :selectedType="$contentType" />
    </div>

    {{-- Playlist mode: title (truncates) + back on the same row --}}
    <div
        class="gallery-header-playlist align-items-center gap-2 min-w-0"
        id="playlistHeader"
        data-layer="gallery-header-playlist"
        hidden
    >
        <h5 id="playlistTitle" class="text-light text-truncate mb-0 flex-grow-1 min-w-0"></h5>
        <button
            type="button"
            id="playlistBackBtn"
            class="btn bg-light text-dark channel-sidebar-toggle-pill flex-shrink-0"
            title="{{ __('common.back') }}"
            aria-label="{{ __('common.back') }}"
            data-layer="playlist-back">
            <i class="bi bi-chevron-left"></i>
            <span class="ms-1">{{ __('common.back') }}</span>
        </button>
    </div>
</div>
