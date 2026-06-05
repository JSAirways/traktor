{{--
    Channel Header Component

    Displays the selected channel name and filter pills for content type filtering.
    Includes mobile toggle button for channel sidebar offcanvas.

    @prop object|null $channel - Selected channel object (or null for "All Videos")
    @prop string $contentType - Currently selected content type: 'all'|'videos'|'playlists' (default: 'all')
    @prop string $currentSlug - Current user slug
    @prop bool $hasChannels - Whether channels exist (more than just "All Videos") (default: false)
--}}
@props([
    'channel' => null,
    'contentType' => 'all',
    'currentSlug' => '',
    'hasChannels' => false,
])

<div class="d-flex align-items-center justify-content-between mb-3" data-layer="channel-header">
    <div class="d-flex align-items-center gap-2" data-layer="channel-header-left">
        @if($hasChannels)
            {{-- Mobile toggle button (hidden on desktop) - Layer: sidebar-toggle --}}
            <button
                class="btn bg-light text-dark channel-sidebar-toggle-pill d-md-none"
                data-bs-toggle="offcanvas"
                data-bs-target="#channelSidebarOffcanvas"
                type="button"
                aria-label="{{ __('gallery.channel_sidebar_title') }}"
                title="{{ __('gallery.channel_sidebar_title') }}"
                data-layer="sidebar-toggle">
                <i class="bi bi-compass fs-5"></i>
            </button>
        @endif
        {{-- Filter pills (always visible, on the left) - Layer: content-filter --}}
        <x-gallery.content-filter-pills :selectedType="$contentType" />
    </div>
    <div class="d-flex align-items-center gap-2" data-layer="channel-header-right">
        {{-- Back button (shown when viewing playlist, hidden by default, positioned on the right) - Layer: playlist-back --}}
        <button
            type="button"
            id="playlistBackBtn"
            class="btn btn-outline-light border-0 d-none"
            title="{{ __('common.back') }}"
            data-layer="playlist-back">
            <i class="bi bi-chevron-left"></i>
            {{ __('common.back') }}
        </button>
        @if($hasChannels)
            {{-- Channel name and thumbnail aligned on the right (desktop only) - Layer: channel-info --}}
            <div class="d-flex align-items-center gap-2 d-none d-md-flex" id="channelHeaderContainer" data-layer="channel-info">
                <h4 id="channelNameHeader" class="text-light mb-0">
                    {{ $channel && $channel->id !== 'all' ? $channel->name : __('gallery.all_videos') }}
                </h4>
                <div id="channelThumbnailContainer" class="channel-sidebar-avatar">
                    {{-- Avatar with image (hidden initially for 'All Videos') --}}
                    <div id="channelAvatarWithImage" class="{{ !$channel || $channel->id === 'all' || !$channel->thumbnail ? 'd-none' : '' }}">
                        <div class="text-center d-flex flex-column align-items-center mb-0 user-avatar-tile">
                            <div class="user-avatar-circle bg-transparent border-0 text-light d-flex align-items-center justify-content-center overflow-hidden">
                                <img id="channelThumbnailImage" src="{{ $channel && $channel->thumbnail ? $channel->thumbnail : '' }}" alt="{{ $channel && $channel->id !== 'all' ? $channel->name : __('gallery.all_videos') }}" class="user-avatar-image" />
                            </div>
                        </div>
                    </div>
                    {{-- Avatar with icon (shown for 'All Videos') --}}
                    <div id="channelAvatarWithIcon" class="{{ $channel && $channel->id !== 'all' && $channel->thumbnail ? 'd-none' : '' }}">
                        <div class="text-center d-flex flex-column align-items-center mb-0 user-avatar-tile">
                            <div class="user-avatar-circle bg-transparent border-0 text-light d-flex align-items-center justify-content-center overflow-hidden">
                                <i class="bi bi-collection-play fs-1 text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

