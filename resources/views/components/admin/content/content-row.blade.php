@props([
    'item' => null, // ContentItem DTO
    'channelId' => 'all',
    'playlistVideos' => [], // Array of videos for playlists
])

@if($item->isPlaylist())
    {{-- Playlist row with accordion pattern --}}
    <tr data-id="{{ $item->id }}" data-type="{{ $item->type }}" data-channel-id="{{ $channelId }}" 
        class="align-middle playlist-row"
        data-playlist-id="{{ $item->id }}">
        {{-- Checkbox (outside clickable area) --}}
        <td class="align-middle" onclick="event.stopPropagation();">
            <input type="checkbox" class="form-check-input content-checkbox" 
                   data-id="{{ $item->id }}" 
                   data-type="{{ $item->type }}"
                   data-channel-id="{{ $channelId }}"
                   onchange="updateBulkActionsToolbar('{{ $channelId }}')">
        </td>
        
        {{-- Drag handle (outside clickable area) --}}
        <td class="drag-handle align-middle text-center" style="cursor: move; touch-action: none;" onclick="event.stopPropagation();">
            <i class="bi bi-grip-vertical text-muted fs-5 d-none d-md-inline"></i>
            <i class="bi bi-three-dots-vertical text-muted fs-4 d-md-none"></i>
        </td>
        
        {{-- Desktop: Thumbnail (clickable) --}}
        <td class="align-middle d-none d-md-table-cell playlist-clickable-cell">
            <img src="{{ $item->thumbnail_url }}" alt="{{ $item->title }}" class="rounded" style="width: 60px; height: 45px; object-fit: cover;">
        </td>
        
        {{-- Desktop: Duration (clickable) --}}
        <td class="align-middle d-none d-md-table-cell playlist-clickable-cell">
            {{ gmdate('H:i:s', $item->duration) }}
        </td>
        
        {{-- Title (clickable) --}}
        <td class="align-middle playlist-clickable-cell">
            <div class="d-flex align-items-center gap-2">
                {{-- Mobile thumbnail --}}
                <img src="{{ $item->thumbnail_url }}" alt="{{ $item->title }}" class="rounded d-md-none" style="width: 40px; height: 30px; object-fit: cover;">
                <span class="fw-bold">{{ $item->title }}</span>
                <span class="text-muted small d-none d-sm-inline">({{ str_replace(':count', $item->video_count ?? 0, __('admin.videos_count')) }})</span>
            </div>
        </td>
        
        {{-- Visible/Actions with chevron on far right --}}
        <td class="align-middle text-end">
            <div class="d-flex align-items-center justify-content-end flex-shrink-0" onclick="event.stopPropagation();">
                <x-ui.form-toggle
                    :action="route('admin.content.toggle-visibility')"
                    :checked="$item->is_visible"
                    :hidden="['id' => $item->id, 'type' => $item->type]"
                />
                
                <x-ui.form-action-button
                    :action="route('admin.content.delete')"
                    :confirm="__('common.are_you_sure')"
                    icon="bi bi-trash"
                    variant="outline-danger"
                    :title="__('common.delete')"
                    :hidden="['id' => $item->id, 'type' => $item->type]"
                />
                
                {{-- Chevron button (far right) - hidden, used only for Bootstrap collapse initialization --}}
                <button 
                    type="button"
                    class="accordion-button collapsed p-0 border-0 bg-transparent shadow-none ms-2 playlist-chevron-btn"
                    data-bs-toggle="collapse"
                    data-bs-target="#playlist-{{ $item->id }}, #playlist-{{ $item->id }}-mobile"
                    aria-expanded="false"
                    aria-controls="playlist-{{ $item->id }} playlist-{{ $item->id }}-mobile"
                    onclick="event.stopPropagation();"
                    style="pointer-events: auto;">
                    <i class="bi bi-chevron-right"></i>
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
        </td>
    </tr>
@else
    {{-- Regular video row (no accordion) --}}
    <tr data-id="{{ $item->id }}" data-type="{{ $item->type }}" data-channel-id="{{ $channelId }}" class="align-middle">
        <td class="align-middle">
            <input type="checkbox" class="form-check-input content-checkbox" 
                   data-id="{{ $item->id }}" 
                   data-type="{{ $item->type }}"
                   data-channel-id="{{ $channelId }}"
                   onchange="updateBulkActionsToolbar('{{ $channelId }}')">
        </td>
        <td class="drag-handle align-middle text-center" style="cursor: move; touch-action: none;">
            <i class="bi bi-grip-vertical text-muted fs-5 d-none d-md-inline"></i>
            <i class="bi bi-three-dots-vertical text-muted fs-4 d-md-none"></i>
        </td>
        <td class="align-middle d-none d-md-table-cell">
            <img src="{{ $item->thumbnail_url }}" alt="{{ $item->title }}" class="rounded" style="width: 60px; height: 45px; object-fit: cover;">
        </td>
        <td class="align-middle d-none d-md-table-cell">{{ gmdate('H:i:s', $item->duration) }}</td>
        <td class="align-middle">
            <div class="d-flex align-items-center gap-2">
                {{-- Mobile thumbnail --}}
                <img src="{{ $item->thumbnail_url }}" alt="{{ $item->title }}" class="rounded d-md-none" style="width: 40px; height: 30px; object-fit: cover;">
                <span class="fw-bold">{{ $item->title }}</span>
            </div>
        </td>
        <td class="align-middle text-end">
            <div class="d-flex align-items-center justify-content-end gap-2">
                <x-ui.form-toggle
                    :action="route('admin.content.toggle-visibility')"
                    :checked="$item->is_visible"
                    :hidden="['id' => $item->id, 'type' => $item->type]"
                />
                
                <x-ui.form-action-button
                    :action="route('admin.content.delete')"
                    :confirm="__('common.are_you_sure')"
                    icon="bi bi-trash"
                    variant="outline-danger"
                    :title="__('common.delete')"
                    :hidden="['id' => $item->id, 'type' => $item->type]"
                />
            </div>
        </td>
    </tr>
@endif
@if($item->isPlaylist() && !empty($playlistVideos[$item->id] ?? []))
    <tr class="no-drag">
        <!-- Mobile version (colspan 3: checkbox, drag, title) -->
        <td colspan="3" class="p-0 d-md-none">
            <div class="accordion-collapse collapse" id="playlist-{{ $item->id }}-mobile">
                <div class="accordion-body ms-2">
                    @foreach($playlistVideos[$item->id] as $video)
                        <x-admin.content.playlist-video-row :video="$video" :isMobile="true" />
                    @endforeach
                </div>
            </div>
        </td>
        <!-- Desktop version (colspan 6: checkbox, drag, thumbnail, duration, title, visible/actions) -->
        <td colspan="6" class="p-0 d-none d-md-table-cell">
            <div class="accordion-collapse collapse" id="playlist-{{ $item->id }}">
                <div class="accordion-body ps-4 pe-1">
                    @foreach($playlistVideos[$item->id] as $video)
                        <x-admin.content.playlist-video-row :video="$video" :isMobile="false" />
                    @endforeach
                </div>
            </div>
        </td>
    </tr>
@endif

