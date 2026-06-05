@props(['playlist'])

@php
    // Handle both object and array inputs
    $id = is_array($playlist) ? $playlist['id'] : $playlist->id;
    $playlistId = is_array($playlist) ? $playlist['playlist_id'] : $playlist->playlist_id;
    $thumbnailUrl = is_array($playlist) ? $playlist['thumbnail_url'] : $playlist->thumbnail_url;
    $title = is_array($playlist) ? $playlist['title'] : $playlist->title;
    $duration = is_array($playlist) ? $playlist['duration'] : $playlist->duration;
    $channelId = is_array($playlist) ? ($playlist['channel_id'] ?? 'all') : ($playlist->channel_id ?? 'all');
@endphp

<div class="col-sm-6 col-md-4 ">
    <div class="ratio ratio-16x9 video-tile overflow-hidden position-relative rounded" 
         data-type="playlist" 
         data-id="{{ $id }}" 
         data-playlist-id="{{ $playlistId }}"
         data-channel-id="{{ $channelId }}"
         data-content-type="playlists"
         {{ $attributes }}>
        <img
            class="img-fluid w-100 h-100 object-fit-cover object-position-center"
            src="{{ $thumbnailUrl }}"
            alt="{{ __('gallery.playlist_thumbnail') }}"
            loading="lazy"
        />
        <div class="position-absolute bottom-0 start-0 end-0 video-overlay">
            <div class="video-overlay-content">
                <p class="video-title mb-1 fw-bold text-light text-truncate d-flex align-items-center gap-2">
                    <i class="bi bi-grid text-light" style="flex-shrink: 0;"></i>
                    {{ $title }}
                </p>
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock text-light" style="flex-shrink: 0;"></i>
                    <span class="text-light small fw-bold">{{ gmdate('H:i:s', $duration) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

