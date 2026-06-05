<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Video;
use App\Models\Playlist;
use App\Services\AssetService;
use App\Services\DeviceRegistrationService;
use App\Services\ViewingSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GalleryController extends Controller
{
    protected ?User $user = null;

    public function __construct(
        protected DeviceRegistrationService $deviceService,
        protected ViewingSessionService $viewingSessionService,
        protected AssetService $assetService
    ) {
    }

    /**
     * Display user's video gallery.
     */
    public function show(Request $request, string $slug)
    {
        // Get or create viewing session using service
        // If session is missing, redirect to welcome (PIN entry happens on profile selection page)
        [$isValid, $user, $redirectRoute] = $this->viewingSessionService->getOrCreateSession($request, $slug, true);
        
        if (!$isValid || !$user) {
            // Session is missing - redirect to welcome page
            // PIN entry should only happen on profile selection page, not here
            return redirect()->route('welcome')
                ->with('error', __('messages.session_expired_enter_pin'));
        }

        // Store user reference for use in buildChannelList
        $this->user = $user;
        
        // Get cache version for cache-busting
        $cacheVersion = $user->getCacheVersionTimestamp();
        $versionedCacheKey = "user_gallery_{$slug}_v{$cacheVersion}";
        
        // Get selected channel from URL parameter (default: 'all')
        $selectedChannelId = $request->query('channel', 'all');
        
        // Cache duration: 24 hours (86400 seconds) - cache versioning handles invalidation
        $content = Cache::remember($versionedCacheKey, 86400, function () use ($user) {

            // Get videos and playlists separately with channel info
            // Select only required columns for better performance
            $videos = $user->videos()
                ->select('id', 'video_id', 'title', 'duration', 'display_order', 'is_visible', 'playlist_id', 'channel_id', 'channel_name', 'channel_thumbnail')
                ->where('is_visible', true)
                ->whereNull('playlist_id')
                ->orderBy('display_order')
                ->get()
                ->map(function ($video) {
                    return (object) [
                        'type' => 'video',
                        'id' => $video->id,
                        'video_id' => $video->video_id,
                        'title' => $video->title,
                        'duration' => $video->duration,
                        'thumbnail_url' => "https://i.ytimg.com/vi/{$video->video_id}/hqdefault.jpg",
                        'display_order' => $video->display_order,
                        'channel_id' => $video->channel_id,
                        'channel_name' => $video->channel_name,
                        'channel_thumbnail' => $video->channel_thumbnail,
                        'model' => $video,
                    ];
                });

            // Select only required columns for playlists
            $playlists = $user->playlists()
                ->select('id', 'playlist_id', 'title', 'total_duration', 'display_order', 'is_visible', 'channel_id', 'channel_name', 'channel_thumbnail')
                ->where('is_visible', true)
                ->orderBy('display_order')
                ->with(['videos' => function ($query) {
                    $query->select('id', 'video_id', 'playlist_id', 'is_visible', 'display_order')
                        ->where('is_visible', true)
                        ->orderBy('display_order');
                }])
                ->get()
                ->map(function ($playlist) {
                    $firstVideo = $playlist->videos->first();
                    $thumbnailVideoId = $firstVideo ? $firstVideo->video_id : substr($playlist->playlist_id, 0, 11);
                    
                    return (object) [
                        'type' => 'playlist',
                        'id' => $playlist->id,
                        'playlist_id' => $playlist->playlist_id,
                        'title' => $playlist->title,
                        'duration' => $playlist->total_duration,
                        'thumbnail_url' => "https://i.ytimg.com/vi/{$thumbnailVideoId}/hqdefault.jpg",
                        'display_order' => $playlist->display_order,
                        'channel_id' => $playlist->channel_id,
                        'channel_name' => $playlist->channel_name,
                        'channel_thumbnail' => $playlist->channel_thumbnail,
                        'model' => $playlist,
                    ];
                });

            return $videos->concat($playlists)
                ->sortBy('display_order')
                ->values();
        });
        
        // Group content by channel and build channel list
        $channels = $this->buildChannelList($content);
        
        // If "All content" section is hidden and no channel specified, default to first channel
        $showAllContent = $user->show_all_content_section ?? false;
        $channelFromUrl = $request->query('channel');
        if (!$showAllContent && $channelFromUrl === null && !empty($channels)) {
            // Default to first available channel (not "All Videos" since it's hidden)
            $selectedChannelId = $channels[0]->id;
        }
        
        // Get selected channel object
        $selectedChannel = null;
        if ($selectedChannelId === 'all') {
            // Only allow "all" if show_all_content_section is enabled
            if ($showAllContent) {
                $selectedChannel = (object) [
                    'id' => 'all',
                    'name' => __('gallery.all_videos'),
                    'thumbnail' => null,
                    'content_count' => $content->count(),
                ];
            } else {
                // "All content" is hidden, but URL explicitly requested it - fallback to first channel
                if (!empty($channels)) {
                    $selectedChannelId = $channels[0]->id;
                    $selectedChannel = $channels[0];
                } else {
                    // No channels available (shouldn't happen, but defensive)
                    $selectedChannel = (object) [
                        'id' => 'all',
                        'name' => __('gallery.all_videos'),
                        'thumbnail' => null,
                        'content_count' => $content->count(),
                    ];
                }
            }
        } else {
            $selectedChannel = collect($channels)->firstWhere('id', $selectedChannelId);
            if (!$selectedChannel) {
                // Channel not found - if "All content" is enabled, fallback to it
                if ($showAllContent) {
                    $selectedChannelId = 'all';
                    $selectedChannel = (object) [
                        'id' => 'all',
                        'name' => __('gallery.all_videos'),
                        'thumbnail' => null,
                        'content_count' => $content->count(),
                    ];
                } else {
                    // "All content" is hidden, fallback to first available channel
                    if (!empty($channels)) {
                        $selectedChannelId = $channels[0]->id;
                        $selectedChannel = $channels[0];
                    } else {
                        // No channels available (shouldn't happen, but defensive)
                        $selectedChannel = (object) [
                            'id' => 'all',
                            'name' => __('gallery.all_videos'),
                            'thumbnail' => null,
                            'content_count' => $content->count(),
                        ];
                    }
                }
            }
        }
        
        // Get content type filter from URL (default: 'all')
        $contentType = $request->query('type', 'all');

        // Generate ETag from content, channel, content type, and cache version for cache validation
        $etag = md5(json_encode($content) . $selectedChannelId . $contentType . $cacheVersion);
        
        // Check if client has matching ETag (304 Not Modified)
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->setEtag($etag);
        }

        $view = view('galleries.index', [
            'user' => $user,
            'content' => $content,
            'channels' => $channels,
            'selectedChannel' => $selectedChannel,
            'selectedChannelId' => $selectedChannelId,
            'contentType' => $contentType,
        ]);
        
        // Cache duration: 24 hours (86400 seconds) - cache versioning handles invalidation
        // Add cache headers for the HTML response
        return response($view)
            ->setEtag($etag)
            ->header('Cache-Control', 'public, max-age=86400, s-maxage=86400, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->setCache([
                'public' => true,
                'max_age' => 86400, // 24 hours
                's_maxage' => 86400,
            ]);
    }

    /**
     * Build channel list from content
     * Groups content by channel_id and creates "All Videos" entry
     * 
     * @param \Illuminate\Support\Collection $content
     * @return array
     */
    private function buildChannelList($content): array
    {
        $channels = [];
        
        // Get user's channel order preference, show_all_content_section setting, and hidden channels
        $user = $this->user ?? null;
        $channelOrder = $user->channel_order ?? [];
        $showAllContent = $user->show_all_content_section ?? false;
        $hiddenChannels = $user->hidden_channels ?? [];
        
        // Group content by channel_id
        $groupedByChannel = $content->groupBy(function ($item) {
            return $item->channel_id ?? 'null';
        });
        
        // Create "All Videos" entry (always first if enabled)
        if ($showAllContent) {
            $allVideosCount = $content->count();
            $channels[] = (object) [
                'id' => 'all',
                'name' => __('gallery.all_videos'),
                'thumbnail' => null,
                'content_count' => $allVideosCount,
            ];
        }
        
        // Create channel entries for each unique channel (excluding null channels and hidden channels)
        $channelMap = [];
        foreach ($groupedByChannel as $channelId => $items) {
            if ($channelId === 'null' || !$channelId) {
                continue; // Skip null channels (they're in "All Videos" if shown)
            }
            
            // Skip hidden channels
            if (in_array($channelId, $hiddenChannels)) {
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
}




