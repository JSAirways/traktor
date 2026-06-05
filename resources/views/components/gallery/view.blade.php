{{--
    Gallery View Component
    
    Main gallery view wrapper component that contains the full gallery structure.
    Includes sidebar, header, and content grid.
    
    @prop object $user - User object
    @prop array $content - Array of content items (videos and playlists)
    @prop array $channels - Array of channel objects
    @prop object|null $selectedChannel - Selected channel object (or null for "All Videos")
    @prop string $selectedChannelId - Currently selected channel ID (default: 'all')
    @prop string $contentType - Currently selected content type: 'all'|'videos'|'playlists' (default: 'all')
    @prop string $initialVisibility - Initial visibility state: 'd-none' or '' (default: '')
--}}
@props([
    'user',
    'content' => [],
    'channels' => [],
    'selectedChannel' => null,
    'selectedChannelId' => 'all',
    'contentType' => 'all',
    'initialVisibility' => '',
])

<div class="container-fluid gallery-view px-2 px-md-3 {{ $initialVisibility }}">
    <div id="backButtonContainer" class="text-center my-3 d-none">
        <button type="button" id="backBtn" class="btn btn-success">{{ __('gallery.back_to_gallery') }}</button>
    </div>
    @if(count($content) === 0)
        <div class="empty-gallery-content d-flex flex-column align-items-center justify-content-center">
            <x-ui.user-avatar 
                image="{{ asset('assets/cats/Watching_Rainy_Day_Sticker_by_Pusheen.gif') }}"
                :title="__('gallery.no_videos_found')"
                variant="normal-lg"
                mb="mb-0"
            />
        </div>
    @else
        @php
            $hasChannels = count($channels ?? []) > 1; // More than just "All Videos"
        @endphp
        
        @if($hasChannels)
            {{-- Two-column layout with sidebar --}}
            <div class="row g-0 gallery-layout">
                {{-- Sidebar Column (desktop only) --}}
                <div class="col-md-3 col-lg-2 ps-0 gallery-sidebar d-none d-md-block">
                    <x-gallery.channel-sidebar 
                        :channels="$channels" 
                        :selectedChannelId="$selectedChannelId"
                        :currentSlug="$user->slug"
                        variant="sidebar"
                    />
                </div>
                
                {{-- Main Content Column --}}
                <div class="col-12 col-md-9 col-lg-10 gallery-main">
                    {{-- Landscape mode controls (positioned on left side) - CSS handles visibility based on orientation --}}
                    <div class="gallery-landscape-controls">
                        {{-- Landscape back button (styled like sidebar toggle) - visible in landscape when playlist is active --}}
                        <button
                            type="button"
                            id="playlistBackBtnLandscape"
                            class="btn bg-light text-dark gallery-landscape-button"
                            title="{{ __('common.back') }}"
                            aria-label="{{ __('common.back') }}">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        @if($hasChannels)
                            {{-- Channel sidebar toggle button --}}
                            <button
                                class="btn bg-light text-dark gallery-landscape-button"
                                data-bs-toggle="offcanvas"
                                data-bs-target="#channelSidebarOffcanvas"
                                type="button"
                                aria-label="{{ __('gallery.channel_sidebar_title') }}"
                                title="{{ __('gallery.channel_sidebar_title') }}">
                                <i class="bi bi-compass"></i>
                            </button>
                        @endif
                        {{-- Filter pills (videos and playlists) --}}
                        <x-gallery.content-filter-pills :selectedType="$contentType" elementId="contentFilterPillsLandscape" />
                    </div>
                    <x-gallery.content-header 
                        :channel="$selectedChannel"
                        :content-type="$contentType"
                        :current-slug="$user->slug"
                        :has-channels="$hasChannels"
                        :channels="$channels"
                        :selectedChannelId="$selectedChannelId"
                    />
                    <div class="gallery-content-scrollable pe-3">
                        <div class="row g-3" id="galleryContent" style="display: none;">
                            @foreach($content as $item)
                                @if($item->type === 'video')
                                    <x-gallery.video-tile :video="(array)$item" />
                                @else
                                    <x-gallery.playlist-tile :playlist="(array)$item" />
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- Single column layout (no channels or only "All Videos") --}}
            <x-gallery.single-column-layout 
                :content="$content"
                :content-type="$contentType"
                :current-slug="$user->slug"
            />
        @endif
    @endif
</div>

