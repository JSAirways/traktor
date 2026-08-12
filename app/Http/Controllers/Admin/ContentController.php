<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\ContentItem;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\InvalidatesUserCache;
use App\Http\Requests\Admin\BulkContentRequest;
use App\Http\Requests\Admin\BulkVisibilityRequest;
use App\Http\Requests\Admin\ReorderChannelsRequest;
use App\Http\Requests\Admin\ReorderContentRequest;
use App\Http\Requests\Admin\StoreVideoRequest;
use App\Models\Playlist;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Jobs\ImportPlaylistJob;
use App\Jobs\ImportVideoJob;
use App\Services\ContentService;
use App\Services\YouTubeService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ContentController extends Controller
{
    use InvalidatesUserCache;

    public function __construct(
        protected YouTubeService $youtubeService,
        protected ContentService $contentService
    ) {
    }
    public function index(Request $request)
    {
        $user = auth()->user();
        $selectedUserId = $request->get('user_id', $user->id);
        
        // Cast to int if not null (request parameters come as strings)
        // If null, use current user's ID as fallback
        $selectedUserId = $selectedUserId !== null ? (int) $selectedUserId : $user->id;
        
        // Get available users for selector
        $availableUsers = $this->getAvailableUsers($user);
        
        // Validate selected user can be managed
        $selectedUser = User::find($selectedUserId);
        if (!$selectedUser || !$user->canManage($selectedUser)) {
            $selectedUserId = $user->id;
            $selectedUser = $user;
        } else {
            $selectedUser = User::findOrFail($selectedUserId);
        }
        
        $content = $this->contentService->getUnifiedContent($selectedUserId);
        
        // Group content by channel
        $channels = $this->contentService->buildChannelList($content, $selectedUserId);
        $contentByChannel = $this->contentService->groupContentByChannel($content);
        $hiddenChannels = $selectedUser->hidden_channels ?? [];
        
        // Pre-load playlist videos for playlists (optimize N+1 queries)
        $playlistIds = $content->filter(fn($item) => $item->isPlaylist())
            ->pluck('id')
            ->toArray();

        $allPlaylistVideos = Video::whereIn('playlist_id', $playlistIds)
            ->where('is_visible', true)
            ->select('id', 'video_id', 'title', 'duration', 'thumbnail_url', 'display_order', 'is_visible', 'playlist_id')
            ->orderBy('playlist_id')
            ->orderBy('display_order')
            ->get()
            ->groupBy('playlist_id');

        $playlistVideos = [];
        foreach ($content as $item) {
            if ($item->isPlaylist()) {
                $playlistVideos[$item->id] = $allPlaylistVideos->get($item->id, collect());
            }
        }
        
        return view('admin.content.index', compact('content', 'contentByChannel', 'channels', 'playlistVideos', 'availableUsers', 'selectedUserId', 'selectedUser', 'hiddenChannels'));
    }
    
    /**
     * Get users available for management by current user.
     */
    protected function getAvailableUsers(User $user): Collection
    {
        if ($user->isAdmin()) {
            // Admins can manage all users
            // Select only required columns for better performance
            return User::select('id', 'username', 'slug', 'email', 'role', 'parent_id', 'profile_picture', 'profile_picture_category')
                ->orderBy('username')
                ->get();
        }
        
        // Parents can manage themselves and their children
        $users = collect([$user]);
        // Use exists() instead of count() for better performance
        if ($user->children()->exists()) {
            $users = $users->merge(
                $user->children()
                    ->select('id', 'username', 'slug', 'email', 'role', 'parent_id', 'profile_picture', 'profile_picture_category')
                    ->orderBy('username')
                    ->get()
            );
        }
        
        return $users;
    }
    


    public function toggleVisibility(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'type' => 'required|in:video,playlist',
        ]);

        if ($request->type === 'playlist') {
            $item = Playlist::findOrFail($request->id);
        } else {
            $item = Video::findOrFail($request->id);
        }

        $item->update(['is_visible' => !$item->is_visible]);
        $this->invalidateUserCache($item->user_id);

        return back()->with('success', __('messages.visibility_updated'));
    }

    public function toggleVideoVisibility(Request $request)
    {
        $request->validate([
            'video_id' => 'required|integer|exists:videos,id',
        ]);

        $video = Video::findOrFail($request->video_id);
        $video->update(['is_visible' => !$video->is_visible]);
        $this->invalidateUserCache($video->user_id);

        return back()->with('success', __('messages.video_visibility_updated'));
    }

    public function delete(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'type' => 'required|in:video,playlist',
        ]);

        if ($request->type === 'playlist') {
            $item = Playlist::findOrFail($request->id);
            $userId = $item->user_id;
            $item->delete();
        } else {
            $item = Video::findOrFail($request->id);
            $userId = $item->user_id;
            $item->delete();
        }
        
        $this->invalidateUserCache($userId);

        return back()->with('success', __('messages.item_deleted'));
    }

    public function addVideo(StoreVideoRequest $request)
    {
        $user = auth()->user();
        $targetUserId = $request->get('user_id', $user->id);
        
        // Cast to int (request parameters come as strings)
        // Ensure it's never null (fallback to current user's ID)
        $targetUserId = $targetUserId !== null ? (int) $targetUserId : $user->id;

        try {
            if (empty(Setting::getApiKey())) {
                return back()->with('error', __('messages.youtube_api_key_not_set'));
            }
            
            $url = $request->url;
            $isPlaylist = $request->boolean('is_playlist');

            if ($this->youtubeService->isPlaylistUrl($url)) {
                if (!$isPlaylist) {
                    // Extract video ID from the playlist URL and add as individual video
                    $videoMetadata = $this->youtubeService->fetchVideoMetadata($url);

                    // Use database transaction for atomicity
                    DB::transaction(function () use ($videoMetadata, $targetUserId) {
                        $this->contentService->createVideoWithChannel($videoMetadata, $targetUserId);
                    });

                    $this->invalidateUserCache($targetUserId);
                    return back()->with('success', __('messages.video_added_from_playlist'));
                }

                $playlistMetadata = $this->youtubeService->fetchPlaylistMetadata($url);
                $playlistVideos = $this->youtubeService->fetchPlaylistVideos($url);

                $playlistId = null;
                DB::transaction(function () use ($playlistMetadata, $playlistVideos, $targetUserId, &$playlistId) {
                    $result = $this->contentService->createPlaylistWithChannel($playlistMetadata, $playlistVideos, $targetUserId);
                    $playlistId = $result['playlist']->id; // Store for cache invalidation
                });
                
                $this->invalidateUserCache($targetUserId);
                return back()->with('success', __('messages.playlist_and_videos_added', ['count' => count($playlistVideos)]));
            } else {
                $videoMetadata = $this->youtubeService->fetchVideoMetadata($url);

                // Use database transaction for atomicity
                DB::transaction(function () use ($videoMetadata, $targetUserId) {
                    $this->contentService->createVideoWithChannel($videoMetadata, $targetUserId);
                });

                $this->invalidateUserCache($targetUserId);
                return back()->with('success', __('messages.video_added'));
            }
        } catch (\Exception $e) {
            \Log::error('Content addition failed', [
                'error' => $e->getMessage(),
                'url' => $url ?? null,
                'user_id' => $targetUserId ?? null,
            ]);
            
            // Return generic error message to user
            return back()->with('error', __('messages.content_add_failed'));
        }
    }

    public function reorder(ReorderContentRequest $request)
    {

        $userIds = [];
        $playlistIds = [];
        DB::transaction(function () use ($request, &$userIds, &$playlistIds) {
            foreach ($request->items as $item) {
                if ($item['type'] === 'playlist') {
                    $playlist = Playlist::find($item['id']);
                    if ($playlist) {
                        $userIds[] = $playlist->user_id;
                        $playlistIds[] = $playlist->id;
                        $playlist->update(['display_order' => $item['order']]);
                    }
                } else {
                    $video = Video::find($item['id']);
                    if ($video) {
                        $userIds[] = $video->user_id;
                        // If video belongs to a playlist, invalidate playlist cache
                        if ($video->playlist_id) {
                            $playlistIds[] = $video->playlist_id;
                        }
                        $video->update(['display_order' => $item['order']]);
                    }
                }
            }
        });
        
        foreach (array_unique($userIds) as $userId) {
            $this->invalidateUserCache($userId);
        }

        return response()->success(null, __('messages.order_updated'));
    }

    public function bulkDelete(BulkContentRequest $request)
    {
        $items = json_decode($request->items, true);
        if (!is_array($items) || empty($items)) {
            return response()->json(['success' => false, 'message' => __('messages.invalid_items_data')]);
        }

        $userId = auth()->user()->isAdmin() ? null : auth()->id();
        $deletedCount = 0;

        $affectedUserIds = [];
        DB::transaction(function () use ($items, $userId, &$deletedCount, &$affectedUserIds) {
            foreach ($items as $item) {
                if (!isset($item['id']) || !isset($item['type'])) {
                    continue;
                }

                if ($item['type'] === 'playlist') {
                    $model = Playlist::find($item['id']);
                    if ($model && ($userId === null || $model->user_id === $userId)) {
                        $affectedUserIds[] = $model->user_id;
                        $model->delete();
                        $deletedCount++;
                    }
                } else {
                    $model = Video::find($item['id']);
                    if ($model && ($userId === null || $model->user_id === $userId)) {
                        $affectedUserIds[] = $model->user_id;
                        $model->delete();
                        $deletedCount++;
                    }
                }
            }
        });
        
        foreach (array_unique($affectedUserIds) as $affectedUserId) {
            $this->invalidateUserCache($affectedUserId);
        }

        return response()->success(null, __('messages.item_deleted'));
    }

    public function bulkVisibility(BulkVisibilityRequest $request)
    {
        $items = json_decode($request->items, true);
        if (!is_array($items) || empty($items)) {
            return response()->error(__('messages.invalid_items_data'), null, 400);
        }

        $userId = auth()->user()->isAdmin() ? null : auth()->id();
        $updatedCount = 0;
        $isVisible = $request->boolean('visible');

        $affectedUserIds = [];
        DB::transaction(function () use ($items, $userId, $isVisible, &$updatedCount, &$affectedUserIds) {
            foreach ($items as $item) {
                if (!isset($item['id']) || !isset($item['type'])) {
                    continue;
                }

                if ($item['type'] === 'playlist') {
                    $model = Playlist::find($item['id']);
                    if ($model && ($userId === null || $model->user_id === $userId)) {
                        $affectedUserIds[] = $model->user_id;
                        $model->update(['is_visible' => $isVisible]);
                        $updatedCount++;
                    }
                } else {
                    $model = Video::find($item['id']);
                    if ($model && ($userId === null || $model->user_id === $userId)) {
                        $affectedUserIds[] = $model->user_id;
                        $model->update(['is_visible' => $isVisible]);
                        $updatedCount++;
                    }
                }
            }
        });
        
        foreach (array_unique($affectedUserIds) as $affectedUserId) {
            $this->invalidateUserCache($affectedUserId);
        }

        return response()->success(null, __('messages.visibility_updated'));
    }

    /**
     * Delete an entire channel and all associated videos and playlists
     */
    public function deleteChannel(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'channel_id' => 'required|string',
        ]);

        $currentUser = auth()->user();
        $targetUserId = (int) $request->user_id;

        // Verify user can manage target user
        $targetUser = User::find($targetUserId);
        if (!$targetUser || !$currentUser->canManage($targetUser)) {
            return back()->with('error', __('messages.unauthorized_action'));
        }

        $channelId = $request->channel_id;

        DB::transaction(function () use ($targetUserId, $channelId) {
            // Delete all videos (standalone and playlist videos) for this user/channel
            Video::where('user_id', $targetUserId)
                ->where('channel_id', $channelId)
                ->delete();

            // Delete all playlists for this user/channel
            Playlist::where('user_id', $targetUserId)
                ->where('channel_id', $channelId)
                ->delete();
        });

        $this->invalidateUserCache($targetUserId);

        return back()->with('success', __('admin.channel_deleted'));
    }


    /**
     * Get existing content IDs for a user
     * Used to check which videos/playlists are already imported
     * AJAX endpoint for channel import modal
     */
    public function getExistingContentIds(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = auth()->user();
        $targetUserId = (int) $request->user_id;

        // Verify user can manage target user
        $targetUser = User::find($targetUserId);
        if (!$targetUser || !$user->canManage($targetUser)) {
            return response()->error(__('messages.unauthorized_action'), null, 403);
        }

        // Single query for all video IDs
        $existingVideoIds = Video::where('user_id', $targetUserId)
            ->pluck('video_id')
            ->toArray();

        // Single query for all playlist IDs
        $existingPlaylistIds = Playlist::where('user_id', $targetUserId)
            ->pluck('playlist_id')
            ->toArray();

        return response()->success([
            'video_ids' => $existingVideoIds,
            'playlist_ids' => $existingPlaylistIds,
        ]);
    }

    /**
     * Fetch channel content (uploads and playlists)
     * AJAX endpoint for channel import modal
     */
    public function fetchChannelContent(Request $request)
    {
        $request->validate([
            'channel_input' => 'required|string',
            'content_type' => 'required|in:uploads,playlists',
            'page_token' => 'nullable|string',
        ]);

        try {
            if (empty(Setting::getApiKey())) {
                return response()->error(__('messages.youtube_api_key_not_set'), null, 400);
            }

            $channelInput = $request->channel_input;
            $contentType = $request->content_type;
            $pageToken = $request->page_token;

            // Resolve channel ID and get channel info
            $channelInfo = $this->youtubeService->resolveChannelId($channelInput);

            if ($contentType === 'uploads') {
                if (empty($channelInfo['uploads_playlist_id'])) {
                    return response()->error(__('messages.channel_no_uploads'), null, 400);
                }

                $result = $this->youtubeService->fetchChannelUploads(
                    $channelInfo['uploads_playlist_id'],
                    $pageToken
                );
            } else {
                $result = $this->youtubeService->fetchChannelPlaylists(
                    $channelInfo['channel_id'],
                    $pageToken
                );
            }

            return response()->success([
                'channel_info' => $channelInfo,
                'items' => $result['items'],
                'next_page_token' => $result['nextPageToken'],
                'total_results' => $result['totalResults'],
            ]);

        } catch (\Exception $e) {
            \Log::error('Channel fetch failed', [
                'error' => $e->getMessage(),
                'channel_input' => $request->channel_input ?? null,
            ]);

            return response()->error($e->getMessage(), null, 400);
        }
    }

    /**
     * Import selected videos/playlists from channel
     * AJAX endpoint for channel import modal
     * Supports both selective import (items array) and bulk import (import_all flag)
     */
    public function importChannelContent(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'import_all' => 'boolean',
            'items' => 'required_without:import_all|array',
            'items.*.type' => 'required_with:items|in:video,playlist',
            'items.*.id' => 'required_with:items|string',
            'channel_id' => 'required_if:import_all,true|string',
            'uploads_playlist_id' => 'nullable|string',
            'content_type' => 'required_if:import_all,true|in:uploads,playlists',
        ]);

        $user = auth()->user();
        $targetUserId = $request->user_id;

        // Verify user can manage target user
        $targetUser = User::find($targetUserId);
        if (!$targetUser || !$user->canManage($targetUser)) {
            return response()->error(__('messages.unauthorized_action'), null, 403);
        }

        try {
            if (empty(Setting::getApiKey())) {
                return response()->error(__('messages.youtube_api_key_not_set'), null, 400);
            }

            $importAll = $request->boolean('import_all');
            $addedCount = 0;
            $errors = [];
            
            // Get channel info if importing from a channel (for bulk import or when channel_id is provided)
            $channelInfo = null;
            if ($request->has('channel_id') && $request->channel_id) {
                try {
                    $channelId = $request->channel_id;
                    $channelThumbnail = $this->youtubeService->getChannelThumbnail($channelId);
                    // Get channel name from channel info
                    $channelData = $this->youtubeService->resolveChannelId($channelId);
                    $channelInfo = [
                        'channel_id' => $channelId,
                        'channel_name' => $channelData['title'] ?? null,
                        'channel_thumbnail' => $channelThumbnail,
                    ];
                } catch (\Exception $e) {
                    \Log::warning('Failed to get channel info for import', [
                        'channel_id' => $request->channel_id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue without channel info - helpers will extract it per item
                }
            }
            
            // If import_all is true, fetch all items from the channel
            if ($importAll) {
                $items = $this->contentService->fetchAllChannelItems($request);
            } else {
                $items = $request->items;
            }

            // Remove duplicate items using service method
            $items = $this->contentService->removeDuplicateItems($items);

            // Batch check for existing items to provide better logging
            $existingItems = $this->contentService->batchCheckExistingItems($items, $targetUserId);
            $existingVideos = $existingItems['existing_videos'];
            $existingPlaylists = $existingItems['existing_playlists'];

            // Dispatch jobs for bulk import (prevents API rate limiting)
            $dispatchedCount = 0;
            $skippedCount = 0;
            
            foreach ($items as $index => $item) {
                // Skip videos that already exist
                if ($item['type'] === 'video' && in_array($item['id'], $existingVideos)) {
                    $skippedCount++;
                    continue;
                }
                
                // Skip playlists that already exist (they'll be updated when accessed)
                if ($item['type'] === 'playlist' && in_array($item['id'], $existingPlaylists)) {
                    $skippedCount++;
                    continue;
                }

                // Dispatch job with staggered delay to prevent rate limiting
                // Stagger by 0.1 seconds per item (10 items per second max)
                $delay = now()->addSeconds($index * 0.1);
                
                if ($item['type'] === 'video') {
                    ImportVideoJob::dispatch($item['id'], $targetUserId, $channelInfo)
                        ->delay($delay);
                    $dispatchedCount++;
                } else {
                    ImportPlaylistJob::dispatch($item['id'], $targetUserId, $channelInfo)
                        ->delay($delay);
                    $dispatchedCount++;
                }
            }

            // Return success message indicating jobs were queued
            if ($dispatchedCount > 0) {
                $message = __('messages.items_queued_for_import', ['count' => $dispatchedCount]);
                if ($skippedCount > 0) {
                    $message .= ' ' . __('messages.items_skipped_already_exist', ['count' => $skippedCount]);
                }
                return response()->success([
                    'dispatched_count' => $dispatchedCount,
                    'skipped_count' => $skippedCount,
                ], $message);
            } else {
                return response()->error(__('messages.no_items_to_import'), [
                    'skipped_count' => $skippedCount,
                ], 400);
            }

        } catch (\Exception $e) {
            \Log::error('Channel import failed', [
                'error' => $e->getMessage(),
                'user_id' => $targetUserId ?? null,
            ]);

            return response()->error(__('messages.import_content_failed'), null, 500);
        }
    }


    /**
     * Reorder channels for a user
     */
    public function reorderChannels(ReorderChannelsRequest $request)
    {
        $user = auth()->user();
        $targetUserId = (int) $request->user_id;
        
        // Validate user can manage target user
        $targetUser = User::find($targetUserId);
        if (!$targetUser || !$user->canManage($targetUser)) {
            return response()->error(__('messages.unauthorized_action'), null, 403);
        }
        
        // Update channel order
        $targetUser->channel_order = $request->channels;
        $targetUser->save();
        
        // Invalidate user gallery cache
        $this->invalidateUserCache($targetUserId);
        
        return response()->success(null, __('messages.channel_order_updated'));
    }

    /**
     * Toggle "All Content" section visibility for a user
     */
    public function toggleAllContentSection(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = auth()->user();
        $targetUserId = (int) $request->user_id;

        // Validate user can manage target user
        $targetUser = User::find($targetUserId);
        if (!$targetUser || !$user->canManage($targetUser)) {
            return response()->error(__('messages.unauthorized_action'), null, 403);
        }

        // Toggle show_all_content_section
        $targetUser->show_all_content_section = !($targetUser->show_all_content_section ?? false);
        $targetUser->save();

        // Invalidate user gallery cache
        $this->invalidateUserCache($targetUserId);

        return back()->with('success', __('messages.order_updated'));
    }

    /**
     * Toggle channel visibility for a user
     */
    public function toggleChannelVisibility(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'channel_id' => 'required|string',
        ]);

        $user = auth()->user();
        $targetUserId = (int) $request->user_id;
        $channelId = $request->channel_id;

        // Validate user can manage target user
        $targetUser = User::find($targetUserId);
        if (!$targetUser || !$user->canManage($targetUser)) {
            return response()->error(__('messages.unauthorized_action'), null, 403);
        }

        // Get current hidden channels array
        $hiddenChannels = $targetUser->hidden_channels ?? [];
        
        // Toggle channel visibility
        if (in_array($channelId, $hiddenChannels)) {
            // Channel is hidden, make it visible (remove from hidden list)
            $hiddenChannels = array_values(array_filter($hiddenChannels, fn($id) => $id !== $channelId));
        } else {
            // Channel is visible, hide it (add to hidden list)
            $hiddenChannels[] = $channelId;
        }
        
        $targetUser->hidden_channels = $hiddenChannels;
        $targetUser->save();

        // Invalidate user gallery cache
        $this->invalidateUserCache($targetUserId);

        return back()->with('success', __('messages.visibility_updated'));
    }
}
