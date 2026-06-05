@props(['video', 'index' => null])

@php
    // Handle both object and array inputs
    $videoId = is_array($video) ? $video['video_id'] : $video->video_id;
    $thumbnailUrl = is_array($video) ? $video['thumbnail_url'] : $video->thumbnail_url;
    $title = is_array($video) ? $video['title'] : $video->title;
    $duration = is_array($video) ? $video['duration'] : $video->duration;
    $channelId = is_array($video) ? ($video['channel_id'] ?? 'all') : ($video->channel_id ?? 'all');
@endphp

<div class="col-sm-6 col-md-4">
    <div class="ratio ratio-16x9 video-tile overflow-hidden position-relative rounded" 
         data-type="video" 
         data-id="{{ $videoId }}"
         data-channel-id="{{ $channelId }}"
         data-content-type="videos"
         @if($index !== null) data-video-index="{{ $index }}" @endif
         {{ $attributes }}>
        <img
            class="img-fluid w-100 h-100 object-fit-cover object-position-center"
            src="{{ $thumbnailUrl }}"
            alt="{{ __('gallery.video_thumbnail') }}"
            loading="lazy"
        />
        <div class="position-absolute bottom-0 start-0 end-0 video-overlay">
            <div class="video-overlay-content">
                <p class="video-title mb-1 fw-bold text-light text-truncate">{{ $title }}</p>
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock text-light" style="flex-shrink: 0;"></i>
                    <span class="text-light small fw-bold">{{ gmdate('H:i:s', $duration) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

