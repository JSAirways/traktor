{{--
    Channel Sidebar Component
    
    Displays a list of channels (including "All Videos") for filtering gallery content.
    Supports both desktop sidebar and mobile offcanvas variants.
    
    @prop array $channels - Array of channel objects with id, name, thumbnail, content_count
    @prop string $selectedChannelId - Currently selected channel ID (default: 'all')
    @prop string $currentSlug - Current user slug for building navigation links
    @prop string $variant - Display variant: 'sidebar'|'offcanvas' (default: 'sidebar')
--}}
@props([
    'channels' => [],
    'selectedChannelId' => 'all',
    'currentSlug' => '',
    'variant' => 'sidebar',
])

@php
    $isOffcanvas = $variant === 'offcanvas';
    $hasChannels = count($channels) > 1; // More than just "All Videos"
@endphp

@if($hasChannels)
    @if($isOffcanvas)
        {{-- Mobile Offcanvas Variant - Layer: mobile-offcanvas --}}
        <div class="offcanvas offcanvas-start bg-dark text-light" tabindex="-1" id="channelSidebarOffcanvas" aria-labelledby="channelSidebarOffcanvasLabel" data-layer="mobile-offcanvas">
            <div class="offcanvas-header border-bottom border-secondary d-flex justify-content-between align-items-center">
                <h5 class="offcanvas-title mb-0" id="channelSidebarOffcanvasLabel">{{ __('gallery.channel_sidebar_title') }}</h5>
                <button type="button" class="btn btn-outline-light border-0 ms-auto" data-bs-dismiss="offcanvas" aria-label="Close">
                    <i class="bi bi-x-lg fs-4"></i>
                </button>
            </div>
            <div class="offcanvas-body p-0">
                <ul class="nav flex-column">
                    @foreach($channels as $channel)
                        <li class="nav-item w-100">
                            <button 
                                type="button"
                                class="nav-link text-start w-100 border-0 bg-transparent text-light d-flex align-items-center gap-2 py-2 {{ $selectedChannelId === $channel->id ? 'active bg-light text-success' : '' }}"
                                data-channel-id="{{ $channel->id }}"
                                data-bs-dismiss="offcanvas">
                                <div class="channel-sidebar-avatar">
                                    @if($channel->id === 'all')
                                        <x-ui.user-avatar 
                                            icon="bi bi-collection-play"
                                            variant="tile"
                                            size="normal"
                                            :show-name="false"
                                            mb="mb-0"
                                            borderColor="none"
                                        />
                                    @else
                                        @if(!empty($channel->thumbnail))
                                            <x-ui.user-avatar 
                                                image="{{ $channel->thumbnail }}"
                                                variant="tile"
                                                size="normal"
                                                :show-name="false"
                                                mb="mb-0"
                                                borderColor="none"
                                            />
                                        @else
                                            <x-ui.user-avatar 
                                                icon="bi bi-collection-play"
                                                variant="tile"
                                                size="normal"
                                                :show-name="false"
                                                mb="mb-0"
                                                borderColor="none"
                                            />
                                        @endif
                                    @endif
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <span class="channel-sidebar-title text-truncate">{{ $channel->name }}</span>
                                </div>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @else
        {{-- Desktop Sidebar Variant - Layer: sidebar-content --}}
        <div class="channel-sidebar-list" data-layer="sidebar-content">
            <ul class="nav flex-column">
                @foreach($channels as $channel)
                    <li class="nav-item w-100">
                        <button 
                            type="button"
                            class="nav-link text-start w-100 border-0 text-light d-flex align-items-center gap-2 py-2 {{ $selectedChannelId === $channel->id ? 'active bg-light text-success' : '' }}"
                            data-channel-id="{{ $channel->id }}">
                            <div class="channel-sidebar-avatar">
                                @if($channel->id === 'all')
                                    <x-ui.user-avatar 
                                        icon="bi bi-collection-play"
                                        variant="tile"
                                        size="normal"
                                        :show-name="false"
                                        mb="mb-0"
                                        borderColor="none"
                                    />
                                @else
                                    @if(!empty($channel->thumbnail))
                                        <x-ui.user-avatar 
                                            image="{{ $channel->thumbnail }}"
                                            variant="tile"
                                            size="normal"
                                            :show-name="false"
                                            mb="mb-0"
                                            borderColor="none"
                                        />
                                    @else
                                        <x-ui.user-avatar 
                                            icon="bi bi-collection-play"
                                            variant="tile"
                                            size="normal"
                                            :show-name="false"
                                            mb="mb-0"
                                            borderColor="none"
                                        />
                                    @endif
                                @endif
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <span class="channel-sidebar-title fw-bold text-truncate">{{ $channel->name }}</span>
                            </div>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endif

