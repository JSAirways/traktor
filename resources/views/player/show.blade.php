@extends('layouts.app')

@section('title', ($playlist ?? null) ? $playlist->title . ' - ' . $user->username . "'s Traktor" : $video->title . ' - ' . $user->username . "'s Traktor")
@section('body-class', 'bg-dark text-light view-player')

@section('content')
<x-layout.navbar-gallery :username="$user->username" :user="$user" />

<!-- Loading Spinner -->
<div id="loadingSpinner" class="position-fixed w-100 d-flex justify-content-center align-items-center bg-dark" style="top: 0; left: 0; bottom: 0; right: 0; z-index: 9999;">
    <x-ui.loading-spinner />
</div>

<!-- Player View Container - Fixed to viewport -->
<div class="player-view">
    <!-- Layer 1: YouTube iframe (bottom layer) -->
    <div id="videoContainer" class="video-container d-flex align-items-center justify-content-center" data-layer="video">
        <div id="player" class="player-iframe"></div>
    </div>
    
    <!-- Layer 2: Click Blocker (intercepts all clicks) -->
    <div class="click-blocker-layer" data-layer="click-blocker"></div>
    
    <!-- Layer 3: Overlay (blur + cat GIF when paused) -->
    <div class="overlay-layer" data-layer="overlay">
        <div class="video-overlay-effect"></div>
        <div class="cat-gif-container d-none">
            <img id="catGif" src="" alt="Paused" />
        </div>
    </div>
    
    <!-- Layer 4: Controls (navbar + control bar) -->
    <div class="controls-layer" data-layer="controls">
        <!-- Control Bar -->
        <div id="customControlBar" class="custom-control-bar">
                <!-- Play/Pause Button -->
                <button type="button" id="customPlayPause" class="custom-control-btn custom-play-pause" aria-label="Play/Pause">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" viewBox="0 0 16 16" class="play-icon">
                        <path d="M6 4v8a.5.5 0 0 0 .811.391l7-4.5a.5.5 0 0 0 0-.782l-7-4.5A.5.5 0 0 0 6 4zm1.5 4.678L11.956 8 7.5 3.322v5.356z"/>
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" viewBox="0 0 16 16" class="pause-icon d-none">
                        <path d="M5.5 3.5A.5.5 0 0 1 6 4v8a.5.5 0 0 1-1 0V4a.5.5 0 0 1 .5-.5zm5 0A.5.5 0 0 1 11 4v8a.5.5 0 0 1-1 0V4a.5.5 0 0 1 .5-.5z"/>
                    </svg>
                </button>
                
                <!-- Progress Container -->
                <div class="custom-progress-container">
                    <div id="customProgressBar" class="custom-progress-bar">
                        <div id="customProgressFill" class="custom-progress-fill"></div>
                    </div>
                </div>
                
                <!-- Time Display -->
                <div id="customTimeDisplay" class="custom-time-display">
                    <span id="currentTime">0:00</span> / <span id="duration">0:00</span>
                </div>
                
                <!-- Fullscreen Button -->
                <button type="button" id="customFullscreen" class="custom-control-btn custom-fullscreen" aria-label="Fullscreen">
                    <i class="bi bi-arrows-fullscreen fs-4"></i>
                </button>
        </div>
    </div>
</div>

<script 
    data-slug="{{ $user->slug }}" 
    data-cat-gifs='{{ json_encode($catGifs) }}'
    data-video-id="{{ $videoId ?? ($currentVideoId ?? (isset($videos) && isset($currentIndex) && $videos->count() > $currentIndex ? $videos[$currentIndex]->video_id : '')) }}"
    @if(isset($video))
        data-video-db-id="{{ $video->id }}"
    @elseif(isset($videos) && isset($currentIndex) && $videos->count() > $currentIndex)
        data-video-db-id="{{ $videos[$currentIndex]->id }}"
    @endif
    data-channel-id="{{ $channelId }}"
    @if(isset($playlist) && isset($playlistId))
        data-playlist-id="{{ $playlistId }}"
        @php
            // Get playlist videos - prefer playlistData, fallback to videos collection
            $playlistVideosArray = [];
            if (isset($playlistData) && is_array($playlistData) && isset($playlistData['videos']) && is_array($playlistData['videos'])) {
                $playlistVideosArray = $playlistData['videos'];
            } elseif (isset($videos) && $videos->count() > 0) {
                $playlistVideosArray = $videos->map(function($v, $i) {
                    return [
                        'id' => $v->id,
                        'video_id' => $v->video_id,
                        'title' => $v->title ?? null,
                        'duration' => $v->duration ?? null,
                        'index' => $i,
                    ];
                })->toArray();
            }
        @endphp
        data-playlist-videos='{{ json_encode($playlistVideosArray) }}'
        data-current-index="{{ $currentIndex ?? 0 }}"
    @endif
></script>

@push('scripts')
    @vite('resources/js/resources/player/show.js')
@endpush
@endsection
