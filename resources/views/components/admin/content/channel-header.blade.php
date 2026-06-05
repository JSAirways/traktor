@props([
    'channel' => null, // Channel object with id, name, thumbnail, videos_count, playlists_count
    'showDragHandle' => false, // Show drag handle for reordering
    'showVisibilityToggle' => false, // Show visibility toggle for channel
    'isChannelVisible' => true, // Whether channel is visible
    'selectedUserId' => null, // Selected user ID for toggle
])

<div {{ $attributes->merge(['class' => 'd-flex align-items-center w-100 gap-2 gap-md-3 me-3']) }}>
    @if($showDragHandle)
        <div class="channel-drag-handle text-muted" style="cursor: move; touch-action: none; flex-shrink: 0;">
            <i class="bi bi-grip-vertical fs-5 d-none d-md-inline"></i>
            <i class="bi bi-three-dots-vertical fs-4 d-md-none"></i>
        </div>
    @endif

    @if($channel->thumbnail ?? null)
        <img src="{{ $channel->thumbnail }}" alt="{{ $channel->name }}"
             class="rounded" style="width: 40px; height: 40px; object-fit: cover; flex-shrink: 0;">
    @else
        <div class="rounded d-flex align-items-center justify-content-center bg-secondary flex-shrink-0" style="width: 40px; height: 40px;">
            <i class="bi bi-collection-play text-light"></i>
        </div>
    @endif

    <span class="fw-bold flex-grow-1">{{ $channel->name ?? __('admin.all_content') }}</span>

    <div class="d-flex align-items-center gap-2 gap-md-3 flex-shrink-0">
        <span class="d-flex align-items-center gap-1 fs-5">
            <i class="bi bi-play-btn text-success"></i>
            <span class="text-muted">{{ $channel->videos_count ?? 0 }}</span>
        </span>
        <span class="d-flex align-items-center gap-1 fs-5">
            <i class="bi bi-collection-play text-success"></i>
            <span class="text-muted">{{ $channel->playlists_count ?? 0 }}</span>
        </span>
    </div>

    {{ $slot ?? '' }}
</div>

