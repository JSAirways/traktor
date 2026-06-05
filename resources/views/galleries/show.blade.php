@extends('layouts.app')

@section('title', $user->username . "'s Traktor")
@section('body-class', 'bg-dark text-light')

@section('content')
<x-layout.navbar-gallery :username="$user->username" :user="$user" />

<!-- Loading Spinner - Layer: loading overlay -->
<div id="loadingSpinner" class="position-fixed w-100 d-flex justify-content-center align-items-center bg-dark" data-layer="loading" style="top: 80px; left: 0; bottom: 0; right: 0; z-index: 9999;">
    <x-ui.loading-spinner />
</div>

<div class="container-fluid gallery-view px-2 px-md-3 main-content-container" data-layer="gallery-container">
    <div id="backButtonContainer" class="text-center my-3 d-none" data-layer="back-button">
        <button type="button" id="backBtn" class="btn btn-success">{{ __('gallery.back_to_gallery') }}</button>
    </div>
    @if(count($content) === 0)
        <div class="empty-gallery-content d-flex flex-column align-items-center justify-content-center" data-layer="empty-state">
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
            <div class="row g-0 gallery-layout" data-layer="layout">
                {{-- Sidebar Column (desktop only) - Layer: sidebar --}}
                <div class="col-md-3 col-lg-2 ps-0 gallery-sidebar d-none d-md-block" data-layer="sidebar">
                    <x-gallery.channel-sidebar 
                        :channels="$channels" 
                        :selectedChannelId="$selectedChannelId"
                        :currentSlug="$user->slug"
                        variant="sidebar"
                    />
                </div>
                
                {{-- Main Content Column - Layer: main-content --}}
                <div class="col-12 col-md-9 col-lg-10 gallery-main" data-layer="main-content">
                    <x-gallery.content-header 
                        :channel="$selectedChannel"
                        :content-type="$contentType"
                        :current-slug="$user->slug"
                        :has-channels="true"
                        :channels="$channels"
                        :selectedChannelId="$selectedChannelId"
                    />
                    <div class="gallery-content-scrollable pe-3" data-layer="content-scrollable">
                        <div class="row g-3" id="galleryContent" data-layer="content-grid" style="display: none;">
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

<script data-slug="{{ $user->slug }}" data-channels='{{ json_encode($channels ?? []) }}' data-username="{{ $user->username }}" data-cache-version="{{ $cacheVersion }}"></script>

@push('scripts')
    @vite('resources/js/resources/galleries/show.js')
@endpush
@endsection

