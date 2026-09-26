@extends('layouts.app')

@section('title', ($playlist ?? null) ? $playlist->title . ' - ' . $user->profile_name . "'s Traktor" : $video->title . ' - ' . $user->profile_name . "'s Traktor")
@section('body-class', 'bg-dark text-light view-player')

@section('content')
<x-layout.navbar-gallery :profileName="$user->profile_name" :user="$user" />

<!-- Loading Spinner -->
<div id="loadingSpinner" class="position-fixed w-100 d-flex justify-content-center align-items-center bg-dark" style="top: 0; left: 0; bottom: 0; right: 0; z-index: 9999;">
    <x-ui.loading-spinner />
</div>

<!-- Player View Container - Fixed to viewport -->
<div class="player-view">
    <x-player.video-container :catGifs="$catGifs ?? []" />

    <div class="controls-layer" data-layer="controls">
        <x-player.control-bar />
    </div>
</div>

<script 
    data-slug="{{ $user->slug }}" 
    data-cat-gifs='{{ json_encode($catGifs) }}'
    data-pause-overlay-enabled="{{ ($pauseOverlayEnabled ?? true) ? '1' : '0' }}"
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
