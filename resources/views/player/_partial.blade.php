{{-- Partial player view for AJAX loading --}}
{{-- This is the content that gets swapped in/out --}}

<!-- Loading Spinner -->
<div id="loadingSpinner" class="position-fixed start-0 w-100 d-flex justify-content-center align-items-center bg-dark" style="top: 0; bottom: 0; z-index: 9999;">
    <x-ui.loading-spinner />
</div>

<!-- Player View Container - Fixed to viewport -->
<div class="player-view">
    <!-- Video Container - Bootstrap flex utilities for centering -->
    <div id="videoContainer" class="video-container d-flex align-items-center justify-content-center">
        <!-- YouTube Player Iframe -->
        <div id="player" class="player-iframe"></div>
        
        <!-- Pause Overlay -->
        <div class="pause-overlay">
            <div class="video-overlay-effect"></div>
            <div class="cat-gif-container d-none">
                <img id="catGif" src="" alt="Paused" />
            </div>
        </div>
        
        <!-- Custom Controls Overlay -->
        <div id="customControls" class="custom-controls">
            <!-- Click Blocker (for double-click fullscreen) -->
            <div class="custom-click-blocker"></div>
        </div>
    </div>
    
    <!-- Control Bar - Outside video container to avoid overflow clipping -->
    <x-player.control-bar />
</div>

@php
    $playlistData = null;
    $currentVideoId = $videoId ?? null;
    if (isset($playlist) && isset($videos)) {
        $playlistData = [
            'id' => $playlist->id,
            'playlist_id' => $playlist->playlist_id,
            'title' => $playlist->title,
            'videos' => $videos->map(function($video, $index) {
                return [
                    'id' => $video->id,
                    'video_id' => $video->video_id,
                    'title' => $video->title,
                    'duration' => $video->duration,
                    'index' => $index,
                ];
            })->toArray(),
        ];
        // Get current video ID from playlist videos
        if (isset($videos[$currentIndex ?? 0])) {
            $currentVideoId = $videos[$currentIndex ?? 0]->video_id;
        }
    }
@endphp

<script 
    data-slug="{{ $user->slug }}" 
    data-cat-gifs='{{ json_encode($catGifs) }}'
    data-video-id="{{ $currentVideoId }}"
    data-channel-id="{{ $channelId }}"
    @if(isset($playlist) && isset($playlistId))
        data-playlist-id="{{ $playlistId }}"
        data-playlist-videos='{{ json_encode($playlistData['videos'] ?? []) }}'
        data-current-index="{{ $currentIndex ?? 0 }}"
    @endif
></script>




