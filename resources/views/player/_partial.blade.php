{{-- Partial player view for AJAX loading --}}

<!-- Loading Spinner -->
<div id="loadingSpinner" class="position-fixed start-0 w-100 d-flex justify-content-center align-items-center bg-dark" style="top: 0; bottom: 0; z-index: 9999;">
    <x-ui.loading-spinner />
</div>

<!-- Player View Container - Fixed to viewport -->
<div class="player-view">
    <x-player.video-container :catGifs="$catGifs ?? []" />

    <div class="controls-layer" data-layer="controls">
        <x-player.control-bar />
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
