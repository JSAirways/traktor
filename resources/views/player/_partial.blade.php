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




