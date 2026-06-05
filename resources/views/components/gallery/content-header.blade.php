{{--
    Content Header Component
    
    Extracted header section that includes channel header, mobile offcanvas, and playlist header.
    Reusable for both two-column and single-column layouts.
    
    @prop object|null $channel - Selected channel object (or null for "All Videos")
    @prop string $contentType - Currently selected content type: 'all'|'videos'|'playlists' (default: 'all')
    @prop string $currentSlug - Current user slug
    @prop bool $hasChannels - Whether channels exist (more than just "All Videos") (default: false)
    @prop array $channels - Array of channel objects
    @prop string $selectedChannelId - Currently selected channel ID (default: 'all')
--}}
@props([
    'channel' => null,
    'contentType' => 'all',
    'currentSlug' => '',
    'hasChannels' => false,
    'channels' => [],
    'selectedChannelId' => 'all',
])

<div class="gallery-header-container" data-layer="header">
    <x-gallery.channel-header 
        :channel="$channel"
        :content-type="$contentType"
        :current-slug="$currentSlug"
        :has-channels="$hasChannels"
    />
    @if($hasChannels)
        {{-- Mobile offcanvas (rendered but hidden on desktop) - Layer: mobile-offcanvas --}}
        <x-gallery.channel-sidebar 
            :channels="$channels" 
            :selectedChannelId="$selectedChannelId"
            :currentSlug="$currentSlug"
            variant="offcanvas"
        />
    @endif
    {{-- Playlist header (shown when viewing playlist videos) - Layer: playlist-header --}}
    <x-gallery.playlist-header :has-channels="$hasChannels" />
</div>




