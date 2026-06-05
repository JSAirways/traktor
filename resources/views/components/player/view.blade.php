{{--
    Player View Component
    
    Main player view wrapper component that contains the full player structure.
    Always in DOM but hidden when not active.
    
    @prop object $user - User object
    @prop object|null $video - Video object (for single video player)
    @prop object|null $playlist - Playlist object (for playlist player)
    @prop array|null $videos - Array of video objects (for playlist player)
    @prop string|null $videoId - Video ID (for single video player)
    @prop int|null $playlistId - Playlist ID (for playlist player)
    @prop int|null $currentIndex - Current video index in playlist (default: 0)
    @prop string $channelId - Channel ID for back navigation (default: 'all')
    @prop array $catGifs - Array of cat GIF filenames for pause overlay
    @prop string $initialVisibility - Initial visibility state: 'd-none' or '' (default: 'd-none')
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
    'initialVisibility' => 'd-none',
])

<div class="player-view {{ $initialVisibility }}">
    <x-player.structure 
        :user="$user"
        :video="$video"
        :playlist="$playlist"
        :videos="$videos"
        :videoId="$videoId"
        :playlistId="$playlistId"
        :currentIndex="$currentIndex"
        :channelId="$channelId"
        :catGifs="$catGifs"
    />
</div>

