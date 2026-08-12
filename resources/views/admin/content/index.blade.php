@extends('layouts.admin')

@section('content')
<h2 class="mb-3">{{ __('admin.content') }}</h2>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 mb-md-4 gap-2">
    @if(isset($availableUsers) && $availableUsers->count() > 0)
        <x-ui.user-selector
            :users="$availableUsers"
            :selected="$selectedUser"
            :route="route('admin.content.index')"
            param="user_id"
            value-key="id"
            id="contentUserSelector"
            :aria-label="__('admin.managing_content_for')"
        />
    @else
        <div></div>
    @endif
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addVideoModal">
            <i class="bi bi-plus me-1"></i>
            {{ __('admin.add_video') }}
        </button>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#channelImportModal">
            <i class="bi bi-youtube me-1"></i>
            {{ __('admin.add_channel') }}
        </button>
    </div>
</div>

@if(count($content) === 0)
    <div class="min-vh-50 d-flex flex-column align-items-center justify-content-center py-3">
        <x-ui.user-avatar 
            image="{{ asset('assets/cats/Tired_Work_Sticker_by_Pusheen.gif') }}"
            :title="__('admin.add_some_videos')"
            variant="normal-lg"
            mb="mb-0"
            :lightTheme="true"
        />
    </div>
@else
@php
    $allContentChannel = collect($channels ?? [])->firstWhere('id', 'all');
    $regularChannels = collect($channels ?? [])->where('id', '!=', 'all')->values();
@endphp

@if($allContentChannel && ($allContentChannel->videos_count > 0 || $allContentChannel->playlists_count > 0))
    <x-admin.content.channel-section 
        :channel="$allContentChannel" 
        :channelContent="collect($content)"
        :playlistVideos="$playlistVideos"
        :selectedUser="$selectedUser"
        :selectedUserId="$selectedUserId"
        :isAccordion="false"
    />
@endif

@if($regularChannels && $regularChannels->count() > 0)
<div class="accordion" id="channelsAccordion">
    @foreach($regularChannels as $channelIndex => $channel)
        @php
            $channelContent = collect($contentByChannel[$channel->id] ?? []);
        @endphp
        
        <x-admin.content.channel-section 
            :channel="$channel" 
            :channelContent="$channelContent"
            :playlistVideos="$playlistVideos"
            :channelIndex="$channelIndex"
            :selectedUser="$selectedUser"
            :selectedUserId="$selectedUserId"
            :hiddenChannels="$hiddenChannels ?? []"
            :isAccordion="true"
        />
    @endforeach
</div>
@endif
@endif

<!-- Add Video Modal -->
<div class="modal fade" id="addVideoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.content.add-video') }}">
                @csrf
                <input type="hidden" name="user_id" value="{{ $selectedUserId ?? auth()->id() }}">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('admin.add_video_or_playlist') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="url" name="url" 
                            placeholder=" " required>
                        <label for="url">{{ __('admin.youtube_url_or_video_id') }}</label>
                        <div class="form-text">{{ __('admin.youtube_url_help') }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_playlist" name="is_playlist" value="1">
                            <label class="form-check-label" for="is_playlist">
                                {{ __('admin.this_is_a_playlist') }}
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                            <button type="submit" class="btn btn-success">{{ __('common.add') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Channel Import Modal -->
<x-modals.channel-import-modal />

@push('scripts')
@vite(['resources/js/admin/content/index.js', 'resources/js/admin/content/channel-import.js', 'resources/js/admin/shared/bulk-actions.js'])
<script>
// Access bulk actions utility from global namespace (iOS 10 compatibility)
var bulkActions = window.Traktor && window.Traktor.Admin && window.Traktor.Admin.bulkActions;
var updateBulkActionsToolbar = bulkActions ? bulkActions.updateBulkActionsToolbar : null;
var updateAllBulkActionsToolbars = bulkActions ? bulkActions.updateAllBulkActionsToolbars : null;
var performBulkActionFn = bulkActions ? bulkActions.performBulkAction : null;
var clearSelectionFn = bulkActions ? bulkActions.clearSelection : null;

// Make bulkAction available globally for inline onclick handlers
window.bulkAction = function(action, channelId) {
    // iOS 10 compatibility: Manual default parameter handling
    if (typeof channelId === 'undefined') channelId = null;
    if (performBulkActionFn) {
        performBulkActionFn(action, channelId, {
            routes: {
                bulkVisibility: '{{ route("admin.content.bulk-visibility") }}',
                bulkDelete: '{{ route("admin.content.bulk-delete") }}'
            }
        });
    }
};

// Make clearSelection available globally
window.clearSelection = function(channelId) {
    // iOS 10 compatibility: Manual default parameter handling
    if (typeof channelId === 'undefined') channelId = null;
    if (clearSelectionFn) {
        clearSelectionFn(channelId);
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    if (updateAllBulkActionsToolbars) {
        updateAllBulkActionsToolbars();
    }
});
</script>
@endpush
@endsection

