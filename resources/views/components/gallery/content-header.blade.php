{{--
    Content Header Component
    
    Gallery header plus mobile channel offcanvas.
    Reusable for both two-column and single-column layouts.
    
    @prop string $contentType - Currently selected content type: 'all'|'videos'|'playlists' (default: 'all')
    @prop string $currentSlug - Current user slug
    @prop bool $hasChannels - Whether channels exist (more than just "All Videos") (default: false)
    @prop array $channels - Array of channel objects
    @prop string $selectedChannelId - Currently selected channel ID (default: 'all')
--}}
@props([
    'contentType' => 'all',
    'currentSlug' => '',
    'hasChannels' => false,
    'channels' => [],
    'selectedChannelId' => 'all',
])

<div class="gallery-header-container" data-layer="header">
    <x-gallery.gallery-header 
        :content-type="$contentType"
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
</div>
