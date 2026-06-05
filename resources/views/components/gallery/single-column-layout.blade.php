{{--
    Single Column Layout Component
    
    Single column layout variant for when no channels or only "All Videos" exists.
    
    @prop array $content - Array of content items (videos and playlists)
    @prop string $contentType - Currently selected content type: 'all'|'videos'|'playlists' (default: 'all')
    @prop string $currentSlug - Current user slug
--}}
@props([
    'content' => [],
    'contentType' => 'all',
    'currentSlug' => '',
])

<div class="gallery-layout-single" data-layer="layout">
    <div class="gallery-main" data-layer="main-content">
        <x-gallery.content-header 
            :channel="null"
            :content-type="$contentType"
            :current-slug="$currentSlug"
            :has-channels="false"
            :channels="[]"
            :selectedChannelId="'all'"
        />
        <div class="gallery-content-scrollable pe-3" data-layer="content-scrollable">
            <div class="row g-3" id="galleryContent" data-layer="content-grid" style="display: none;">
                @foreach($content as $item)
                    @if($item->type === 'video')
                        <x-gallery.video-tile :video="(array)$item" />
                    @else
                        <x-gallery.playlist-tile :playlist="(array)$item" />
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>




