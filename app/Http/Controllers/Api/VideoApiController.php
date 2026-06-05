<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Models\User;
use App\Models\Video;
use App\Services\DeviceRegistrationService;
use App\Services\ViewingSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class VideoApiController extends Controller
{
    protected DeviceRegistrationService $deviceService;
    protected ViewingSessionService $viewingSessionService;

    public function __construct(
        DeviceRegistrationService $deviceService,
        ViewingSessionService $viewingSessionService
    ) {
        $this->deviceService = $deviceService;
        $this->viewingSessionService = $viewingSessionService;
    }

    /**
     * Get user's visible videos and playlists
     * Returns HTML fragments if Accept header includes text/html, otherwise JSON
     * 
     * Security: Requires valid viewing session matching the slug
     */
    public function getUserVideos(string $slug, Request $request)
    {
        // Get or create viewing session using service
        [$isValid, $user] = $this->viewingSessionService->getOrCreateSession($request, $slug, true);
        
        if (!$isValid || !$user) {
            return response()->error(__('messages.unauthorized_access'), null, 403);
        }
        
        // Get optional query parameters for filtering and pagination
        $channelId = $request->query('channel_id'); // nullable string, 'all' for all videos
        $contentType = $request->query('content_type', 'all'); // 'all'|'videos'|'playlists'
        $page = $request->query('page', null); // nullable int
        $perPage = $request->query('per_page', 50); // int, default 50
        
        // Get cache version for cache-busting
        $cacheVersion = $user->getCacheVersionTimestamp();
        
        // Cache key based on slug, channel, content type, page, per_page, and cache version
        $cacheKey = "user_videos_{$slug}_" . ($channelId ?? 'all') . "_" . $contentType . "_p{$page}_pp{$perPage}_v{$cacheVersion}";
        
        // Cache duration: 24 hours (86400 seconds) - cache versioning handles invalidation
        // Get from cache or compute
        $result = Cache::remember($cacheKey, 86400, function () use ($slug, $channelId, $contentType, $page, $perPage) {
            $user = User::where('slug', $slug)
                ->where('is_viewable', true)
                ->firstOrFail();

            // Get user's hidden channels and "All Content" section visibility
            $hiddenChannels = $user->hidden_channels ?? [];
            $showAllContent = $user->show_all_content_section ?? false;
            
            // If requesting 'all' channel but "All Content" section is hidden, return empty
            if (($channelId === null || $channelId === 'all') && !$showAllContent) {
                return collect([]);
            }
            
            // If requesting a specific hidden channel, return empty
            if ($channelId && $channelId !== 'all' && in_array($channelId, $hiddenChannels)) {
                return collect([]);
            }

            // Build video query
            $videoQuery = $user->videos()
                ->select('id', 'video_id', 'title', 'duration', 'display_order', 'is_visible', 'channel_id')
                ->where('is_visible', true)
                ->whereNull('playlist_id');
            
            // Filter by channel if provided (and not 'all')
            if ($channelId && $channelId !== 'all') {
                $videoQuery->where('channel_id', $channelId);
            } else {
                // If requesting 'all', exclude content from hidden channels
                if (!empty($hiddenChannels)) {
                    $videoQuery->where(function ($query) use ($hiddenChannels) {
                        $query->whereNull('channel_id')
                            ->orWhereNotIn('channel_id', $hiddenChannels);
                    });
                }
            }
            
            // Filter by content type
            if ($contentType === 'videos') {
                // Only return videos (already filtered by whereNull('playlist_id'))
            } elseif ($contentType === 'playlists') {
                // Return empty for videos when filtering playlists
                $videoQuery->whereRaw('1=0'); // Empty result
            }
            
            $videos = $videoQuery->orderBy('display_order')
                ->get()
                ->map(function ($video) {
                    // Generate hqdefault thumbnail URL from video_id
                    $thumbnailUrl = "https://i.ytimg.com/vi/{$video->video_id}/hqdefault.jpg";
                    return [
                        'id' => $video->id,
                        'video_id' => $video->video_id,
                        'title' => $video->title,
                        'duration' => $video->duration,
                        'thumbnail_url' => $thumbnailUrl,
                        'type' => 'video',
                        'display_order' => $video->display_order,
                        'channel_id' => $video->channel_id,
                    ];
                });

            // Build playlist query
            $playlistQuery = $user->playlists()
                ->select('id', 'playlist_id', 'title', 'total_duration', 'display_order', 'is_visible', 'channel_id')
                ->where('is_visible', true);
            
            // Filter by channel if provided (and not 'all')
            if ($channelId && $channelId !== 'all') {
                $playlistQuery->where('channel_id', $channelId);
            } else {
                // If requesting 'all', exclude content from hidden channels
                if (!empty($hiddenChannels)) {
                    $playlistQuery->where(function ($query) use ($hiddenChannels) {
                        $query->whereNull('channel_id')
                            ->orWhereNotIn('channel_id', $hiddenChannels);
                    });
                }
            }
            
            // Filter by content type
            if ($contentType === 'videos') {
                // Return empty for playlists when filtering videos
                $playlistQuery->whereRaw('1=0'); // Empty result
            } elseif ($contentType === 'playlists') {
                // Only return playlists (no additional filter needed)
            }
            
            $playlists = $playlistQuery->orderBy('display_order')
                ->with(['videos' => function ($query) {
                    $query->select('id', 'video_id', 'playlist_id', 'is_visible', 'display_order')
                        ->where('is_visible', true)
                        ->orderBy('display_order');
                }])
                ->get()
                ->map(function ($playlist) {
                    // For playlist thumbnails, use the first video's thumbnail or generate from playlist ID
                    // Since we don't have direct playlist thumbnail URL, we'll use a placeholder approach
                    // Use already loaded relationship to avoid N+1 query
                    $firstVideo = $playlist->videos->first();
                    $thumbnailUrl = $firstVideo 
                        ? "https://i.ytimg.com/vi/{$firstVideo->video_id}/hqdefault.jpg"
                        : "https://i.ytimg.com/vi/{$playlist->playlist_id}/hqdefault.jpg";
                        
                    return [
                        'id' => $playlist->id,
                        'playlist_id' => $playlist->playlist_id,
                        'title' => $playlist->title,
                        'duration' => $playlist->total_duration,
                        'thumbnail_url' => $thumbnailUrl,
                        'type' => 'playlist',
                        'display_order' => $playlist->display_order,
                        'channel_id' => $playlist->channel_id,
                    ];
                });

            // Combine and sort by display_order
            $allItems = $videos->concat($playlists)->sortBy('display_order')->values();
            
            // Apply pagination if requested
            if ($page !== null) {
                $total = $allItems->count();
                $offset = ($page - 1) * $perPage;
                $paginatedItems = $allItems->slice($offset, $perPage)->values();
                
                return [
                    'items' => $paginatedItems,
                    'pagination' => [
                        'current_page' => (int) $page,
                        'per_page' => (int) $perPage,
                        'total' => $total,
                        'last_page' => (int) ceil($total / $perPage),
                    ],
                ];
            }
            
            return [
                'items' => $allItems,
                'pagination' => null,
            ];
        });

        // Extract items and pagination from result
        $items = $result['items'];
        $pagination = $result['pagination'] ?? null;

        // Generate ETag from data and cache version
        $etag = md5(json_encode($items) . $cacheVersion . ($pagination ? json_encode($pagination) : ''));
        
        // Check if client has matching ETag
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->setEtag($etag);
        }

        // Check if client wants HTML
        if ($request->accepts('text/html')) {
            $html = '';
            foreach ($items as $item) {
                if ($item['type'] === 'video') {
                    $html .= View::make('components.gallery.video-tile', ['video' => $item])->render();
                } else {
                    $html .= View::make('components.gallery.playlist-tile', ['playlist' => $item])->render();
                }
            }
            
            return response($html)
                ->setEtag($etag)
                ->header('Content-Type', 'text/html')
                ->header('X-Cache-Version', $cacheVersion) // Include cache version in header for client-side updates
                ->header('Cache-Control', 'public, max-age=86400, s-maxage=86400, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->setCache([
                    'public' => true,
                    'max_age' => 86400, // 24 hours
                    's_maxage' => 86400,
                ]);
        }

        // Return JSON for backwards compatibility
        // Include cache version in response so JavaScript can update appState
        $responseData = [
            'items' => $items,
            'cache_version' => $cacheVersion, // Include cache version for client-side updates
        ];
        
        if ($pagination) {
            $responseData['pagination'] = $pagination;
        }
        
        return response()->json($responseData)
          ->setEtag($etag)
          ->header('X-Cache-Version', $cacheVersion) // Include cache version in header for client-side monitoring
          ->header('Cache-Control', 'public, max-age=86400, s-maxage=86400, must-revalidate')
          ->header('Pragma', 'no-cache')
          ->setCache([
              'public' => true,
              'max_age' => 86400, // 24 hours
              's_maxage' => 86400,
          ]);
    }

    /**
     * Get videos in a playlist
     * Returns HTML fragments if Accept header includes text/html, otherwise JSON
     * 
     * Security: Requires valid viewing session for the playlist's owner
     */
    public function getPlaylistVideos(int $playlistId, Request $request)
    {
        // First check if playlist exists at all (for better error messages)
        $playlist = Playlist::where('id', $playlistId)
            ->with('user') // Eager load user to avoid N+1 query
            ->first();
        
        if (!$playlist) {
            return response()->error('Playlist not found (ID: ' . $playlistId . '). It may have been deleted or is not accessible.', null, 404);
        }
        
        // Check if playlist is visible
        if (!$playlist->is_visible) {
            return response()->error('Playlist not found (ID: ' . $playlistId . '). It may have been deleted or is not accessible.', null, 404);
        }
        
        // Verify playlist has an owner (should never be null due to foreign key, but defensive check)
        if (!$playlist->user) {
            return response()->error(__('messages.unauthorized_access'), null, 403);
        }
        
        // Validate viewing session matches the playlist owner's slug
        // Use getOrCreateSession (like getUserVideos) to allow auto-creation if device is registered
        $ownerSlug = $playlist->user->slug;
        [$isValid, $sessionUser, $redirectTo] = $this->viewingSessionService->getOrCreateSession($request, $ownerSlug, true);
        
        // If session user was found but session wasn't created, and it matches the playlist owner,
        // allow access (user is authenticated, just session wasn't auto-created)
        if (!$isValid && $sessionUser && $sessionUser->id === $playlist->user_id) {
            // User matches playlist owner - create session now
            $this->viewingSessionService->createSession($request, $sessionUser);
            $isValid = true;
        }
        
        if (!$isValid || !$sessionUser) {
            return response()->error(__('messages.unauthorized_access'), null, 403);
        }
        
        // Get cache version for cache-busting
        $user = $playlist->user;
        $cacheVersion = $user ? $user->getCacheVersionTimestamp() : 0;
        
        // Cache key for playlist videos (includes cache version)
        $cacheKey = "playlist_videos_{$playlistId}_v{$cacheVersion}";
        
        // Cache duration: 24 hours (86400 seconds) - cache versioning handles invalidation
        // Get from cache or compute
        $responseData = Cache::remember($cacheKey, 86400, function () use ($playlist) {
            // Select only required columns for better performance
            $videos = $playlist->videos()
                ->select('id', 'video_id', 'title', 'duration', 'display_order', 'is_visible')
                ->where('is_visible', true)
                ->orderBy('display_order')
                ->get()
                ->map(function ($video, $index) {
                    // Generate hqdefault thumbnail URL from video_id (more reliable)
                    $thumbnailUrl = "https://i.ytimg.com/vi/{$video->video_id}/hqdefault.jpg";
                    
                    return [
                        'id' => $video->id,
                        'video_id' => $video->video_id,
                        'title' => $video->title,
                        'duration' => $video->duration,
                        'thumbnail_url' => $thumbnailUrl,
                        'display_order' => $video->display_order,
                        'index' => $index, // Add index for playlist videos
                    ];
                });

            return [
                'playlist' => [
                    'id' => $playlist->id,
                    'title' => $playlist->title,
                    'total_duration' => $playlist->total_duration,
                ],
                'videos' => $videos,
            ];
        });

        // Generate ETag from data and cache version
        $etag = md5(json_encode($responseData) . $cacheVersion);
        
        // Check if client has matching ETag
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->setEtag($etag);
        }

        // Check if client wants HTML
        if ($request->accepts('text/html')) {
            $html = '';
            foreach ($responseData['videos'] as $video) {
                $html .= View::make('components.gallery.video-tile', ['video' => $video, 'index' => $video['index']])->render();
            }
            
            return response($html)
                ->setEtag($etag)
                ->header('Content-Type', 'text/html')
                ->header('X-Cache-Version', $cacheVersion) // Include cache version in header for client-side updates
                ->header('Cache-Control', 'public, max-age=86400, s-maxage=86400, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->setCache([
                    'public' => true,
                    'max_age' => 86400, // 24 hours
                    's_maxage' => 86400,
                ]);
        }

        // Return JSON for backwards compatibility
        return response()->json($responseData)
            ->setEtag($etag)
            ->header('X-Cache-Version', $cacheVersion) // Include cache version in header for client-side updates
            ->header('Cache-Control', 'public, max-age=86400, s-maxage=86400, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->setCache([
                'public' => true,
                'max_age' => 86400, // 24 hours
                's_maxage' => 86400,
            ]);
    }
}
