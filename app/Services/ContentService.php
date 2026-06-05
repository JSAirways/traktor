<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\ContentItem;
use App\Models\Playlist;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ContentService
{
    public function __construct(
        protected YouTubeService $youtubeService
    ) {
    }

    /**
     * Get unified content (videos and playlists) for a user.
     * 
     * @param int|null $userId User ID (null for admin to see all)
     * @param int|null $page Page number for pagination (null for all)
     * @param int|null $perPage Items per page (null for all)
     * @return Collection|array Collection if not paginated, array with 'items' and 'pagination' if paginated
     */
    public function getUnifiedContent(?int $userId = null, ?int $page = null, ?int $perPage = null): Collection|array
    {
        $currentUser = auth()->user();
        
        // Determine which user's content to show
        if ($userId === null) {
            $userId = $currentUser->isAdmin() ? null : $currentUser->id;
        } else {
            // Validate user can manage this user
            $targetUser = User::find($userId);
            if (!$targetUser || !$currentUser->canManage($targetUser)) {
                $userId = $currentUser->id;
            }
        }
        
        // Build base query for videos
        $videoQuery = Video::select('id', 'title', 'duration', 'thumbnail_url', 'display_order', 'is_visible', 'user_id', 'playlist_id', 'channel_id', 'channel_name', 'channel_thumbnail')
            ->whereNull('playlist_id')
            ->when($userId, fn($q) => $q->where('user_id', $userId));
        
        // Build base query for playlists
        $playlistQuery = Playlist::select('id', 'title', 'total_duration as duration', 'thumbnail_url', 'display_order', 'is_visible', 'user_id', 'channel_id', 'channel_name', 'channel_thumbnail')
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->withCount('videos');
        
        // If pagination is requested
        if ($page !== null && $perPage !== null) {
            // Get all items first (needed for accurate total count and sorting)
            $allVideos = (clone $videoQuery)->get();
            $allPlaylists = (clone $playlistQuery)->get();
            
            // Convert to ContentItem objects
            $videoItems = $allVideos->map(fn($video) => new ContentItem(
                'video',
                $video->id,
                $video->title,
                $video->duration ?? 0,
                $video->thumbnail_url,
                $video->display_order,
                $video->is_visible,
                $video->user_id,
                null,
                null,
                $video,
                $video->channel_id,
                $video->channel_name,
                $video->channel_thumbnail
            ));
            
            $playlistItems = $allPlaylists->map(fn($playlist) => new ContentItem(
                'playlist',
                $playlist->id,
                $playlist->title,
                ($playlist->duration ?? 0), // Use alias from query: 'total_duration as duration'
                $playlist->thumbnail_url,
                $playlist->display_order,
                $playlist->is_visible,
                $playlist->user_id,
                $playlist->id,
                $playlist->videos_count,
                $playlist,
                $playlist->channel_id,
                $playlist->channel_name,
                $playlist->channel_thumbnail
            ));
            
            // Combine and sort
            $allItems = $videoItems->concat($playlistItems)->sortBy('display_order')->values();
            
            // Apply pagination
            $total = $allItems->count();
            $offset = ($page - 1) * $perPage;
            $paginatedItems = $allItems->slice($offset, $perPage)->values();
            
            return [
                'items' => $paginatedItems,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $perPage),
                ],
            ];
        }
        
        // Non-paginated: Get individual videos (not in playlists)
        $videos = (clone $videoQuery)->get()
            ->map(fn($video) => new ContentItem(
                'video',
                $video->id,
                $video->title,
                $video->duration ?? 0,
                $video->thumbnail_url,
                $video->display_order,
                $video->is_visible,
                $video->user_id,
                null,
                null,
                $video,
                $video->channel_id,
                $video->channel_name,
                $video->channel_thumbnail
            ));

        // Get playlists
        $playlists = (clone $playlistQuery)->get()
            ->map(fn($playlist) => new ContentItem(
                'playlist',
                $playlist->id,
                $playlist->title,
                ($playlist->duration ?? $playlist->total_duration ?? 0), // Use alias from query, fallback to original property
                $playlist->thumbnail_url,
                $playlist->display_order,
                $playlist->is_visible,
                $playlist->user_id,
                $playlist->id,
                $playlist->videos_count,
                $playlist,
                $playlist->channel_id,
                $playlist->channel_name,
                $playlist->channel_thumbnail
            ));

        // Combine and sort by display_order
        return $videos->concat($playlists)->sortBy('display_order')->values();
    }

    /**
     * Get playlist videos with caching.
     * 
     * @param int $playlistId Playlist ID
     * @return Collection Collection of Video models
     */
    public function getPlaylistVideos(int $playlistId): Collection
    {
        // Get playlist to access owner's cache version
        $playlist = Playlist::find($playlistId);
        if (!$playlist) {
            return collect([]);
        }
        
        // Get cache version for cache-busting
        $cacheVersion = $playlist->user ? $playlist->user->getCacheVersionTimestamp() : 0;
        
        // Cache playlist videos with versioning for automatic invalidation
        $cacheKey = "admin_playlist_videos_{$playlistId}_v{$cacheVersion}";
        return Cache::remember($cacheKey, 300, function () use ($playlistId) {
            // Select only required columns for better performance
            return Video::select('id', 'video_id', 'title', 'duration', 'thumbnail_url', 'display_order', 'is_visible', 'playlist_id')
                ->where('playlist_id', $playlistId)
                ->orderBy('display_order')
                ->get();
        });
    }

    /**
     * Increment display_order for all existing videos and playlists by 1.
     * This makes room for new items to be added at the top (display_order = 1).
     * 
     * @param int|null $userId User ID
     * @return void
     */
    public function incrementAllDisplayOrders(?int $userId = null): void
    {
        if ($userId === null) {
            $userId = auth()->id();
        }
        
        // Increment all standalone videos (not in playlists)
        // Use DB::raw to handle NULL and 0 values properly
        DB::table('videos')
            ->where('user_id', $userId)
            ->whereNull('playlist_id')
            ->update(['display_order' => DB::raw('COALESCE(display_order, 0) + 1')]);
        
        // Increment all playlists
        DB::table('playlists')
            ->where('user_id', $userId)
            ->update(['display_order' => DB::raw('COALESCE(display_order, 0) + 1')]);
    }

    /**
     * Create a video with channel information.
     * Shared helper method used by both addVideo() and importChannelContent().
     * 
     * If channel info is not provided, it will be fetched from YouTube.
     * If the channel already exists for this user (in videos or playlists), 
     * the existing channel info will be reused for consistency.
     *
     * @param array $videoMetadata Video metadata from YouTubeService
     * @param int $targetUserId Target user ID
     * @param array|null $channelInfo Channel info (channel_id, channel_name, channel_thumbnail)
     * @return Video Created video model
     */
    public function createVideoWithChannel(array $videoMetadata, int $targetUserId, ?array $channelInfo = null): Video
    {
        // Extract channel info if not provided
        if (!$channelInfo) {
            try {
                $channelInfo = $this->youtubeService->getVideoChannelInfo($videoMetadata['video_id']);
            } catch (\Exception $e) {
                \Log::warning('Failed to get channel info for video', [
                    'video_id' => $videoMetadata['video_id'],
                    'error' => $e->getMessage(),
                ]);
                $channelInfo = [
                    'channel_id' => null,
                    'channel_name' => null,
                    'channel_thumbnail' => null,
                ];
            }
        }

        // Check if channel already exists for this user (in videos or playlists)
        // If it exists, reuse the existing channel info for consistency
        if (!empty($channelInfo['channel_id'])) {
            $existingChannel = Video::where('user_id', $targetUserId)
                ->where('channel_id', $channelInfo['channel_id'])
                ->whereNotNull('channel_id')
                ->first();
            
            // If not found in videos, check playlists
            if (!$existingChannel) {
                $existingPlaylist = Playlist::where('user_id', $targetUserId)
                    ->where('channel_id', $channelInfo['channel_id'])
                    ->whereNotNull('channel_id')
                    ->first();
                
                if ($existingPlaylist) {
                    // Use existing channel info from playlist
                    $channelInfo = [
                        'channel_id' => $existingPlaylist->channel_id,
                        'channel_name' => $existingPlaylist->channel_name,
                        'channel_thumbnail' => $existingPlaylist->channel_thumbnail,
                    ];
                }
            } else {
                // Use existing channel info from video
                $channelInfo = [
                    'channel_id' => $existingChannel->channel_id,
                    'channel_name' => $existingChannel->channel_name,
                    'channel_thumbnail' => $existingChannel->channel_thumbnail,
                ];
            }
        }

        // Shift all existing items down by 1 to make room at the top
        $this->incrementAllDisplayOrders($targetUserId);

        return Video::create([
            'user_id' => $targetUserId,
            'channel_id' => $channelInfo['channel_id'] ?? null,
            'channel_name' => $channelInfo['channel_name'] ?? null,
            'channel_thumbnail' => $channelInfo['channel_thumbnail'] ?? null,
            'video_id' => $videoMetadata['video_id'],
            'title' => $videoMetadata['title'],
            'duration' => $videoMetadata['duration'],
            'thumbnail_url' => $videoMetadata['thumbnail_url'],
            'display_order' => 1,
            'is_visible' => true,
        ]);
    }

    /**
     * Remove duplicate items from an array based on type and id.
     * 
     * @param array $items Array of items with 'type' and 'id' keys
     * @return array Array of unique items
     */
    public function removeDuplicateItems(array $items): array
    {
        $seenItems = [];
        $uniqueItems = [];
        
        foreach ($items as $item) {
            $itemKey = ($item['type'] ?? 'unknown') . '_' . ($item['id'] ?? '');
            if (!isset($seenItems[$itemKey])) {
                $seenItems[$itemKey] = true;
                $uniqueItems[] = $item;
            }
        }
        
        return $uniqueItems;
    }

    /**
     * Batch check which items already exist in the database.
     * 
     * @param array $items Array of items with 'type' and 'id' keys
     * @param int $targetUserId Target user ID
     * @return array Array with 'existing_videos' and 'existing_playlists' keys containing arrays of IDs
     */
    public function batchCheckExistingItems(array $items, int $targetUserId): array
    {
        $videoIds = [];
        $playlistIds = [];
        
        foreach ($items as $item) {
            if ($item['type'] === 'video') {
                $videoIds[] = $item['id'];
            } elseif ($item['type'] === 'playlist') {
                $playlistIds[] = $item['id'];
            }
        }
        
        $existingVideos = [];
        $existingPlaylists = [];
        
        if (!empty($videoIds)) {
            $existingVideos = Video::where('user_id', $targetUserId)
                ->whereIn('video_id', $videoIds)
                ->pluck('video_id')
                ->toArray();
        }
        
        if (!empty($playlistIds)) {
            $existingPlaylists = Playlist::where('user_id', $targetUserId)
                ->whereIn('playlist_id', $playlistIds)
                ->pluck('playlist_id')
                ->toArray();
        }
        
        return [
            'existing_videos' => $existingVideos,
            'existing_playlists' => $existingPlaylists,
        ];
    }

    /**
     * Create a playlist with channel information.
     * Shared helper method used by both addVideo() and importChannelContent().
     * 
     * @param array $playlistMetadata Playlist metadata from YouTubeService
     * @param array $playlistVideos Array of video metadata for playlist videos
     * @param int $targetUserId Target user ID
     * @param array|null $channelInfo Channel info (channel_id, channel_name, channel_thumbnail)
     * @return array Array with 'action' => 'created'|'updated', 'playlist' => Playlist model
     */
    public function createPlaylistWithChannel(array $playlistMetadata, array $playlistVideos, int $targetUserId, ?array $channelInfo = null): array
    {
        // Check if playlist already exists for this user (prevent duplicates)
        // Use lockForUpdate to prevent race conditions in transactions
        $existingPlaylist = Playlist::where('user_id', $targetUserId)
            ->where('playlist_id', $playlistMetadata['playlist_id'])
            ->lockForUpdate()
            ->first();

        if ($existingPlaylist) {
            // Playlist already exists - update it with latest metadata and videos
            // Do NOT increment display orders or create new playlist - just update existing

            // Extract channel info if not provided
            if (!$channelInfo) {
                try {
                    $channelInfo = $this->youtubeService->getPlaylistChannelInfo($playlistMetadata['playlist_id']);
                } catch (\Exception $e) {
                    \Log::warning('Failed to get channel info for playlist update', [
                        'playlist_id' => $playlistMetadata['playlist_id'],
                        'error' => $e->getMessage(),
                    ]);
                    // Keep existing channel info if we can't fetch new
                    $channelInfo = [
                        'channel_id' => $existingPlaylist->channel_id,
                        'channel_name' => $existingPlaylist->channel_name,
                        'channel_thumbnail' => $existingPlaylist->channel_thumbnail,
                    ];
                }
            }

            // Update playlist metadata (preserve display_order and is_visible - don't move to top or change visibility)
            $existingPlaylist->update([
                'channel_id' => $channelInfo['channel_id'] ?? $existingPlaylist->channel_id,
                'channel_name' => $channelInfo['channel_name'] ?? $existingPlaylist->channel_name,
                'channel_thumbnail' => $channelInfo['channel_thumbnail'] ?? $existingPlaylist->channel_thumbnail,
                'title' => $playlistMetadata['title'],
                'thumbnail_url' => $playlistMetadata['thumbnail_url'],
                'total_duration' => array_sum(array_column($playlistVideos, 'duration')),
                'is_visible' => $existingPlaylist->is_visible, // Explicitly preserve visibility
            ]);

            // Delete existing playlist videos
            $existingPlaylist->videos()->delete();

            // Create new playlist videos
            foreach ($playlistVideos as $index => $videoData) {
                Video::create([
                    'user_id' => $targetUserId,
                    'channel_id' => $channelInfo['channel_id'] ?? null,
                    'channel_name' => $channelInfo['channel_name'] ?? null,
                    'channel_thumbnail' => $channelInfo['channel_thumbnail'] ?? null,
                    'video_id' => $videoData['video_id'],
                    'title' => $videoData['title'],
                    'duration' => $videoData['duration'],
                    'thumbnail_url' => $videoData['thumbnail_url'],
                    'playlist_id' => $existingPlaylist->id,
                    'display_order' => $index + 1,
                    'is_visible' => true,
                ]);
            }

            // Return updated playlist
            $playlist = $existingPlaylist->fresh(['videos']);
            return [
                'action' => 'updated',
                'playlist' => $playlist,
            ];
        }

        // Extract channel info if not provided
        if (!$channelInfo) {
            try {
                $channelInfo = $this->youtubeService->getPlaylistChannelInfo($playlistMetadata['playlist_id']);
            } catch (\Exception $e) {
                \Log::warning('Failed to get channel info for playlist', [
                    'playlist_id' => $playlistMetadata['playlist_id'],
                    'error' => $e->getMessage(),
                ]);
                $channelInfo = [
                    'channel_id' => null,
                    'channel_name' => null,
                    'channel_thumbnail' => null,
                ];
            }
        }

        // Shift all existing items down by 1 to make room at the top
        $this->incrementAllDisplayOrders($targetUserId);

        $playlist = Playlist::create([
            'user_id' => $targetUserId,
            'channel_id' => $channelInfo['channel_id'] ?? null,
            'channel_name' => $channelInfo['channel_name'] ?? null,
            'channel_thumbnail' => $channelInfo['channel_thumbnail'] ?? null,
            'playlist_id' => $playlistMetadata['playlist_id'],
            'title' => $playlistMetadata['title'],
            'thumbnail_url' => $playlistMetadata['thumbnail_url'],
            'total_duration' => array_sum(array_column($playlistVideos, 'duration')),
            'display_order' => 1,
            'is_visible' => true,
        ]);

        // Create playlist videos
        foreach ($playlistVideos as $index => $videoData) {
            Video::create([
                'user_id' => $targetUserId,
                'channel_id' => $channelInfo['channel_id'] ?? null,
                'channel_name' => $channelInfo['channel_name'] ?? null,
                'channel_thumbnail' => $channelInfo['channel_thumbnail'] ?? null,
                'video_id' => $videoData['video_id'],
                'title' => $videoData['title'],
                'duration' => $videoData['duration'],
                'thumbnail_url' => $videoData['thumbnail_url'],
                'playlist_id' => $playlist->id,
                'display_order' => $index + 1,
                'is_visible' => true,
            ]);
        }

        return [
            'action' => 'created',
            'playlist' => $playlist,
        ];
    }

    /**
     * Fetch all items from a channel (all pages).
     * Used for bulk import functionality.
     * 
     * @param Request $request Request with content_type, channel_id, uploads_playlist_id
     * @return array Array of items with type, id, and title
     */
    public function fetchAllChannelItems(Request $request): array
    {
        $contentType = $request->content_type;
        $allItems = [];
        $pageToken = null;
        
        try {
            do {
                if ($contentType === 'uploads') {
                    $result = $this->youtubeService->fetchChannelUploads(
                        $request->uploads_playlist_id,
                        $pageToken
                    );
                } else {
                    $result = $this->youtubeService->fetchChannelPlaylists(
                        $request->channel_id,
                        $pageToken
                    );
                }
                
                // Convert items to the format expected by import logic
                foreach ($result['items'] as $item) {
                    $allItems[] = [
                        'type' => $item['type'],
                        'id' => $item['video_id'] ?? $item['playlist_id'],
                        'title' => $item['title']
                    ];
                }
                
                $pageToken = $result['nextPageToken'];
            } while ($pageToken);
            
            return $allItems;
        } catch (\Exception $e) {
            \Log::error('Failed to fetch all channel items', [
                'error' => $e->getMessage(),
                'content_type' => $contentType,
            ]);
            throw new \Exception('Failed to fetch all items from channel. Please try again.');
        }
    }

    /**
     * Build channel list from content.
     * Groups content by channel_id and creates "All Content" entry.
     * Respects user's channel_order preference and show_all_content_section setting.
     * 
     * @param Collection $content Collection of ContentItem objects
     * @param int|null $userId User ID
     * @return array Array of channel objects
     */
    public function buildChannelList(Collection $content, ?int $userId = null): array
    {
        $channels = [];
        
        // Get user's channel order preference and hidden channels
        $user = $userId ? User::find($userId) : auth()->user();
        $channelOrder = $user->channel_order ?? [];
        $showAllContent = $user->show_all_content_section ?? false;
        $hiddenChannels = $user->hidden_channels ?? [];
        
        // Group content by channel_id
        $groupedByChannel = $content->groupBy(function ($item) {
            return $item->channel_id ?? 'null';
        });
        
        // Create "All Content" entry
        // NOTE: In admin view, "All Content" is always shown (regardless of show_all_content_section)
        // so it can be toggled back to visible. The show_all_content_section toggle only affects frontend display.
        $allContentCount = $content->count();
        $videosCount = $content->filter(fn($item) => $item->isVideo())->count();
        $playlistsCount = $content->filter(fn($item) => $item->isPlaylist())->count();
        
        $channels[] = (object) [
            'id' => 'all',
            'name' => __('admin.all_content'),
            'thumbnail' => null,
            'content_count' => $allContentCount,
            'videos_count' => $videosCount,
            'playlists_count' => $playlistsCount,
        ];
        
        // Create channel entries for each unique channel (excluding null channels)
        // NOTE: In admin view, hidden channels are still shown so they can be toggled back to visible
        $channelMap = [];
        foreach ($groupedByChannel as $channelId => $items) {
            if ($channelId === 'null' || !$channelId) {
                continue; // Skip null channels (they're in "All Content" if shown)
            }
            
            // Don't skip hidden channels in admin view - they need to be visible for management
            
            // Count videos and playlists separately
            $videosCount = $items->filter(fn($item) => $item->isVideo())->count();
            $playlistsCount = $items->filter(fn($item) => $item->isPlaylist())->count();
            
            // Skip channels with no content (shouldn't happen, but defensive)
            if ($videosCount === 0 && $playlistsCount === 0) {
                continue;
            }
            
            // Find first item with non-null thumbnail, or use first item for name
            $firstItem = $items->first();
            $channelName = $firstItem->channel_name ?? 'Unknown Channel';
            
            // Find first item with a non-null thumbnail
            $itemWithThumbnail = $items->first(function ($item) {
                return !empty($item->channel_thumbnail);
            });
            
            $channelMap[$channelId] = (object) [
                'id' => $channelId,
                'name' => $channelName,
                'thumbnail' => $itemWithThumbnail->channel_thumbnail ?? null,
                'content_count' => $items->count(),
                'videos_count' => $videosCount,
                'playlists_count' => $playlistsCount,
            ];
        }
        
        // Sort channels according to user's order preference
        if (!empty($channelOrder)) {
            // Filter out channels that no longer exist
            $channelOrder = array_filter($channelOrder, fn($id) => isset($channelMap[$id]));
            
            // Add ordered channels first
            foreach ($channelOrder as $channelId) {
                if (isset($channelMap[$channelId])) {
                    $channels[] = $channelMap[$channelId];
                    unset($channelMap[$channelId]);
                }
            }
            
            // Add any new channels not in order preference (append to end)
            if (!empty($channelMap)) {
                usort($channelMap, function ($a, $b) {
                    return strcmp($a->name, $b->name);
                });
                $channels = array_merge($channels, array_values($channelMap));
            }
        } else {
            // No order preference, sort alphabetically
            $channelArray = array_values($channelMap);
            usort($channelArray, function ($a, $b) {
                return strcmp($a->name, $b->name);
            });
            $channels = array_merge($channels, $channelArray);
        }
        
        return $channels;
    }

    /**
     * Group content by channel for display.
     * 
     * @param Collection $content Collection of ContentItem objects
     * @return array Array keyed by channel_id (or 'all')
     */
    public function groupContentByChannel(Collection $content): array
    {
        $grouped = [];
        
        // Group by channel_id
        foreach ($content as $item) {
            $channelId = $item->channel_id ?? 'null';
            if (!isset($grouped[$channelId])) {
                $grouped[$channelId] = [];
            }
            $grouped[$channelId][] = $item;
        }
        
        // Sort items within each channel by display_order
        foreach ($grouped as $channelId => $items) {
            usort($grouped[$channelId], function ($a, $b) {
                return $a->display_order <=> $b->display_order;
            });
        }
        
        return $grouped;
    }
}

