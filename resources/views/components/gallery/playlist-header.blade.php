{{--
    Playlist Header Component
    
    Displays playlist title and video count when viewing playlist videos.
    Shows below channel header - channel name remains visible, playlist title is additional info.
    Visible on both mobile and desktop.
    
    @prop bool $hasChannels - Whether channels exist (more than just "All Videos") (default: false)
--}}
@props([
    'hasChannels' => false,
])

{{-- Playlist header component - visible on both mobile and desktop - Layer: playlist-header --}}
{{-- playlist-active class added by JavaScript when playlist is loaded --}}
<div class="d-flex flex-column mb-3 d-none playlist-active" id="playlistHeader" data-layer="playlist-header">
    <h5 id="playlistTitle" class="text-light mb-1"></h5>
</div>

