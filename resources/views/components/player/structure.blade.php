{{--
    Player Structure Component
    
    Player structure containing video container, controls, and control bar.
    Contains the player iframe and all overlays.
    
    @prop object $user - User object
    @prop object|null $video - Video object (for single video player)
    @prop object|null $playlist - Playlist object (for playlist player)
    @prop array|null $videos - Array of video objects (for playlist player)
    @prop string|null $videoId - Video ID (for single video player)
    @prop int|null $playlistId - Playlist ID (for playlist player)
    @prop int|null $currentIndex - Current video index in playlist (default: 0)
    @prop string $channelId - Channel ID for back navigation (default: 'all')
    @prop array $catGifs - Array of cat GIF filenames for pause overlay
--}}
@props([
    'user',
    'video' => null,
    'playlist' => null,
    'videos' => null,
    'videoId' => null,
    'playlistId' => null,
    'currentIndex' => 0,
    'channelId' => 'all',
    'catGifs' => [],
])

<x-player.video-container :catGifs="$catGifs" />
<x-player.control-bar />

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




