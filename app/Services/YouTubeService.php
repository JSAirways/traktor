<?php

namespace App\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;

class YouTubeService
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = Setting::getApiKey() ?? '';
        if (empty($this->apiKey)) {
            throw new Exception("YouTube API Key is not configured. Please set it in the Settings section.");
        }
    }

    /**
     * Extract video ID from YouTube URL
     */
    protected function extractVideoId(string $url): ?string
    {
        // Handle various YouTube URL formats
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches)) {
            return $matches[1];
        }
        return $url; // Assume it's already a video ID if no URL pattern matches
    }

    /**
     * Check if URL is a playlist
     */
    public function isPlaylistUrl(string $url): bool
    {
        return strpos($url, 'list=') !== false || strpos($url, '/playlist') !== false;
    }

    /**
     * Extract playlist ID from URL
     */
    protected function extractPlaylistId(string $url): ?string
    {
        if (preg_match('/[&?]list=([^"&?\/\s]+)/', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('/\/playlist\?list=([^"&?\/\s]+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Fetch video metadata from YouTube API
     * Enhanced error handling with fallback and logging
     * Results are cached for 1 hour since video metadata rarely changes
     */
    public function fetchVideoMetadata(string $videoId): array
    {
        $videoId = $this->extractVideoId($videoId);
        
        // Cache video metadata for 1 hour (videos rarely change)
        return \Illuminate\Support\Facades\Cache::remember("youtube_video_{$videoId}", 3600, function () use ($videoId) {
            try {
                $url = "https://www.googleapis.com/youtube/v3/videos";
                $response = Http::timeout(10)->get($url, [
                    'key' => $this->apiKey,
                    'id' => $videoId,
                    'part' => 'snippet,contentDetails,status'
                ]);

                if (!$response->successful()) {
                    \Log::warning('YouTube API request failed', [
                        'status' => $response->status(),
                        'video_id' => $videoId,
                    ]);
                    throw new Exception("Failed to fetch video data. Please check the URL and try again.");
                }

                $data = $response->json();
                
                if (isset($data['error'])) {
                    $errorMessage = $data['error']['message'] ?? 'Unknown error';
                    \Log::warning('YouTube API error', [
                        'error' => $errorMessage,
                        'video_id' => $videoId,
                    ]);
                    throw new Exception("YouTube API Error: " . $errorMessage);
                }

                if (empty($data['items'])) {
                    throw new Exception("Video not found. Please check the URL and try again.");
                }

                $item = $data['items'][0];
                $snippet = $item['snippet'];
                $contentDetails = $item['contentDetails'];
                $status = $item['status'] ?? [];

                // Check if video is embeddable
                if (isset($status['embeddable']) && $status['embeddable'] === false) {
                    throw new Exception("This video cannot be embedded and played outside of YouTube. The video owner has restricted embedding.");
                }

                // Extract thumbnail (prefer default.jpg)
                $thumbnails = $snippet['thumbnails'];
                $thumbnailUrl = $thumbnails['default']['url'] ?? $thumbnails['medium']['url'] ?? $thumbnails['high']['url'] ?? '';

                // Convert ISO 8601 duration to seconds
                $duration = $this->parseDuration($contentDetails['duration'] ?? '');

                return [
                    'video_id' => $videoId,
                    'title' => $snippet['title'],
                    'duration' => $duration,
                    'thumbnail_url' => $thumbnailUrl,
                ];
            } catch (\Exception $e) {
                // Re-throw with enhanced context
                if (strpos($e->getMessage(), 'Failed to fetch') === false && 
                    strpos($e->getMessage(), 'YouTube API Error') === false &&
                    strpos($e->getMessage(), 'Video not found') === false &&
                    strpos($e->getMessage(), 'cannot be embedded') === false) {
                    // Network or timeout error
                    \Log::error('YouTube API network error', [
                        'error' => $e->getMessage(),
                        'video_id' => $videoId ?? null,
                    ]);
                    throw new Exception("Network error while fetching video data. Please try again later.");
                }
                throw $e;
            }
        });
    }

    /**
     * Fetch multiple videos metadata (for batch operations)
     */
    public function fetchMultipleVideoMetadata(array $videoIds): array
    {
        $results = [];
        $chunks = array_chunk($videoIds, 50); // YouTube API limit

        foreach ($chunks as $chunk) {
            $url = "https://www.googleapis.com/youtube/v3/videos";
            $response = Http::get($url, [
                'key' => $this->apiKey,
                'id' => implode(',', $chunk),
                'part' => 'snippet,contentDetails,status'
            ]);

            if (!$response->successful()) {
                continue; // Skip failed chunks
            }

            $data = $response->json();
            
            if (isset($data['error']) || empty($data['items'])) {
                continue;
            }

            foreach ($data['items'] as $item) {
                $snippet = $item['snippet'];
                $contentDetails = $item['contentDetails'];
                $status = $item['status'] ?? [];

                // Skip videos that are not embeddable
                if (isset($status['embeddable']) && $status['embeddable'] === false) {
                    continue; // Skip non-embeddable videos in playlists
                }

                $thumbnails = $snippet['thumbnails'];
                $thumbnailUrl = $thumbnails['default']['url'] ?? $thumbnails['medium']['url'] ?? '';

                $duration = $this->parseDuration($contentDetails['duration'] ?? '');

                $results[] = [
                    'video_id' => $item['id'],
                    'title' => $snippet['title'],
                    'duration' => $duration,
                    'thumbnail_url' => $thumbnailUrl,
                ];
            }
        }

        return $results;
    }

    /**
     * Fetch playlist metadata
     * Enhanced error handling with fallback and logging
     * Results are cached for 1 hour since playlist metadata rarely changes
     */
    public function fetchPlaylistMetadata(string $playlistId): array
    {
        $playlistId = $this->extractPlaylistId($playlistId) ?? $playlistId;
        
        // Cache playlist metadata for 1 hour (playlists rarely change)
        return \Illuminate\Support\Facades\Cache::remember("youtube_playlist_{$playlistId}", 3600, function () use ($playlistId) {
            try {
                $url = "https://www.googleapis.com/youtube/v3/playlists";
                $response = Http::timeout(10)->get($url, [
                    'key' => $this->apiKey,
                    'id' => $playlistId,
                    'part' => 'snippet'
                ]);

                if (!$response->successful()) {
                    \Log::warning('YouTube API playlist request failed', [
                        'status' => $response->status(),
                        'playlist_id' => $playlistId,
                    ]);
                    throw new Exception("Failed to fetch playlist data. Please check the URL and try again.");
                }

                $data = $response->json();
                
                if (isset($data['error']) || empty($data['items'])) {
                    $errorMessage = $data['error']['message'] ?? 'Playlist not found';
                    \Log::warning('YouTube API playlist error', [
                        'error' => $errorMessage,
                        'playlist_id' => $playlistId,
                    ]);
                    throw new Exception("Playlist not found. Please check the URL and try again.");
                }

                $item = $data['items'][0];
                $snippet = $item['snippet'];

                $thumbnails = $snippet['thumbnails'];
                $thumbnailUrl = $thumbnails['default']['url'] ?? $thumbnails['medium']['url'] ?? '';

                return [
                    'playlist_id' => $playlistId,
                    'title' => $snippet['title'],
                    'thumbnail_url' => $thumbnailUrl,
                ];
            } catch (\Exception $e) {
                // Re-throw with enhanced context
                if (strpos($e->getMessage(), 'Failed to fetch') === false && 
                    strpos($e->getMessage(), 'Playlist not found') === false) {
                    // Network or timeout error
                    \Log::error('YouTube API network error (playlist)', [
                        'error' => $e->getMessage(),
                        'playlist_id' => $playlistId ?? null,
                    ]);
                    throw new Exception("Network error while fetching playlist data. Please try again later.");
                }
                throw $e;
            }
        });
    }

    /**
     * Fetch all videos in a playlist
     * Enhanced error handling with fallback and logging
     */
    public function fetchPlaylistVideos(string $playlistId): array
    {
        try {
            $playlistId = $this->extractPlaylistId($playlistId) ?? $playlistId;
            $videoIds = [];
            $nextPageToken = '';

            // Fetch all video IDs from playlist
            do {
                $url = "https://www.googleapis.com/youtube/v3/playlistItems";
                $response = Http::timeout(10)->get($url, [
                    'key' => $this->apiKey,
                    'playlistId' => $playlistId,
                    'part' => 'contentDetails',
                    'maxResults' => 50,
                    'pageToken' => $nextPageToken
                ]);

                if (!$response->successful()) {
                    \Log::warning('YouTube API playlist items request failed', [
                        'status' => $response->status(),
                        'playlist_id' => $playlistId,
                    ]);
                    throw new Exception("Failed to fetch playlist items. Please check the URL and try again.");
                }

                $data = $response->json();
                
                if (isset($data['error'])) {
                    $errorMessage = $data['error']['message'] ?? 'Unknown error';
                    \Log::warning('YouTube API playlist items error', [
                        'error' => $errorMessage,
                        'playlist_id' => $playlistId,
                    ]);
                    throw new Exception("YouTube API Error: " . $errorMessage);
                }

                foreach ($data['items'] ?? [] as $item) {
                    if (isset($item['contentDetails']['videoId'])) {
                        $videoIds[] = $item['contentDetails']['videoId'];
                    }
                }

                $nextPageToken = $data['nextPageToken'] ?? '';
            } while ($nextPageToken);

            // Fetch metadata for all videos
            return $this->fetchMultipleVideoMetadata($videoIds);
        } catch (\Exception $e) {
            // Re-throw with enhanced context
            if (strpos($e->getMessage(), 'Failed to fetch') === false && 
                strpos($e->getMessage(), 'YouTube API Error') === false) {
                // Network or timeout error
                \Log::error('YouTube API network error (playlist videos)', [
                    'error' => $e->getMessage(),
                    'playlist_id' => $playlistId ?? null,
                ]);
                throw new Exception("Network error while fetching playlist videos. Please try again later.");
            }
            throw $e;
        }
    }

    /**
     * Parse ISO 8601 duration to seconds
     */
    protected function parseDuration(string $duration): int
    {
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches);
        $hours = isset($matches[1]) ? (int)$matches[1] : 0;
        $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
        $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
        
        return $hours * 3600 + $minutes * 60 + $seconds;
    }

    /**
     * Derive an auto-generated channel playlist ID from a channel ID.
     *
     * YouTube uses fixed prefixes (not officially documented in Data API v3):
     * - UU   = all public uploads (videos + Shorts + live)
     * - UULF = long-form videos only
     * - UUSH = Shorts only
     * - UULV = live streams only
     *
     * relatedPlaylists.uploads returns UU. For channel import we prefer UULF so
     * Shorts never appear — duration/title heuristics are unreliable (Shorts can
     * exceed 60s and often omit #shorts).
     */
    public function deriveChannelPlaylistId(string $channelId, string $prefix = 'UULF'): ?string
    {
        if (!str_starts_with($channelId, 'UC') || strlen($channelId) < 3) {
            return null;
        }

        return $prefix . substr($channelId, 2);
    }

    /**
     * Normalize channel payload: prefer long-form videos playlist (UULF) over UU uploads.
     */
    protected function formatChannelInfo(array $item): array
    {
        $channelId = $item['id'];
        $officialUploads = $item['contentDetails']['relatedPlaylists']['uploads'] ?? null;

        return [
            'channel_id' => $channelId,
            'title' => $item['snippet']['title'],
            // Prefer UULF (videos only); fall back to official UU uploads playlist
            'uploads_playlist_id' => $this->deriveChannelPlaylistId($channelId, 'UULF') ?? $officialUploads,
        ];
    }

    /**
     * Resolve channel input (URL, handle, username, or ID) to channel ID
     * Supports various formats:
     * - Channel ID: UCxxxxxx
     * - Channel URL: youtube.com/channel/UCxxxxxx
     * - Handle: @username or youtube.com/@username
     * - Legacy username: youtube.com/user/username
     */
    public function resolveChannelId(string $input): array
    {
        try {
            $input = trim($input);
            
            // Direct channel ID (starts with UC)
            if (preg_match('/^UC[\w-]{22}$/', $input)) {
                return $this->getChannelInfo($input);
            }
            
            // Extract from channel URL
            if (preg_match('/youtube\.com\/channel\/(UC[\w-]{22})/', $input, $matches)) {
                return $this->getChannelInfo($matches[1]);
            }
            
            // Handle format (@username or youtube.com/@username)
            if (preg_match('/@([\w-]+)/', $input, $matches)) {
                return $this->getChannelByHandle($matches[1]);
            }
            
            // Legacy username format (youtube.com/user/username or youtube.com/c/username)
            if (preg_match('/youtube\.com\/(?:user|c)\/([\w-]+)/', $input, $matches)) {
                return $this->getChannelByUsername($matches[1]);
            }
            
            // Try as direct username/handle
            return $this->getChannelByHandle($input);
            
        } catch (\Exception $e) {
            \Log::error('Channel resolution error', [
                'input' => $input,
                'error' => $e->getMessage(),
            ]);
            throw new Exception("Could not find channel. Please check the URL or channel name and try again.");
        }
    }

    /**
     * Get channel info by channel ID
     */
    protected function getChannelInfo(string $channelId): array
    {
        $url = "https://www.googleapis.com/youtube/v3/channels";
        $response = Http::timeout(10)->get($url, [
            'key' => $this->apiKey,
            'id' => $channelId,
            'part' => 'snippet,contentDetails'
        ]);

        if (!$response->successful()) {
            throw new Exception("Failed to fetch channel information.");
        }

        $data = $response->json();
        
        if (empty($data['items'])) {
            throw new Exception("Channel not found.");
        }

        return $this->formatChannelInfo($data['items'][0]);
    }

    /**
     * Get channel info by handle (@username)
     */
    protected function getChannelByHandle(string $handle): array
    {
        $handle = ltrim($handle, '@');
        
        $url = "https://www.googleapis.com/youtube/v3/channels";
        $response = Http::timeout(10)->get($url, [
            'key' => $this->apiKey,
            'forHandle' => $handle,
            'part' => 'snippet,contentDetails'
        ]);

        if (!$response->successful() || empty($response->json()['items'])) {
            // Fallback to username search
            return $this->getChannelByUsername($handle);
        }

        $data = $response->json();

        return $this->formatChannelInfo($data['items'][0]);
    }

    /**
     * Get channel info by legacy username
     */
    protected function getChannelByUsername(string $username): array
    {
        $url = "https://www.googleapis.com/youtube/v3/channels";
        $response = Http::timeout(10)->get($url, [
            'key' => $this->apiKey,
            'forUsername' => $username,
            'part' => 'snippet,contentDetails'
        ]);

        if (!$response->successful()) {
            throw new Exception("Failed to fetch channel information.");
        }

        $data = $response->json();
        
        if (empty($data['items'])) {
            throw new Exception("Channel not found.");
        }

        return $this->formatChannelInfo($data['items'][0]);
    }

    /**
     * Fetch uploads from a channel (paginated)
     * Returns basic info from playlistItems (no full video metadata)
     */
    public function fetchChannelUploads(string $uploadsPlaylistId, ?string $pageToken = null): array
    {
        try {
            $url = "https://www.googleapis.com/youtube/v3/playlistItems";
            $response = Http::timeout(10)->get($url, [
                'key' => $this->apiKey,
                'playlistId' => $uploadsPlaylistId,
                'part' => 'snippet,contentDetails',
                'maxResults' => 50,
                'pageToken' => $pageToken ?? ''
            ]);

            if (!$response->successful()) {
                \Log::warning('YouTube API channel uploads request failed', [
                    'status' => $response->status(),
                    'playlist_id' => $uploadsPlaylistId,
                ]);
                throw new Exception("Failed to fetch channel uploads.");
            }

            $data = $response->json();
            
            if (isset($data['error'])) {
                throw new Exception("YouTube API Error: " . ($data['error']['message'] ?? 'Unknown error'));
            }

            // Extract video IDs
            $videoIds = [];
            $videos = [];
            foreach ($data['items'] ?? [] as $item) {
                $snippet = $item['snippet'];
                $thumbnails = $snippet['thumbnails'];
                $videoId = $item['contentDetails']['videoId'];
                
                $videoIds[] = $videoId;
                
                $videos[] = [
                    'video_id' => $videoId,
                    'title' => $snippet['title'],
                    'thumbnail_url' => $thumbnails['default']['url'] ?? $thumbnails['medium']['url'] ?? '',
                    'type' => 'video',
                    'duration' => null // Will be populated below
                ];
            }

            // Batch fetch video details (including duration) if we have video IDs
            if (!empty($videoIds)) {
                try {
                    $videoDetailsUrl = "https://www.googleapis.com/youtube/v3/videos";
                    $videoDetailsResponse = Http::timeout(10)->get($videoDetailsUrl, [
                        'key' => $this->apiKey,
                        'id' => implode(',', $videoIds),
                        'part' => 'contentDetails'
                    ]);

                    if ($videoDetailsResponse->successful()) {
                        $videoDetailsData = $videoDetailsResponse->json();
                        $durationMap = [];
                        
                        foreach ($videoDetailsData['items'] ?? [] as $videoItem) {
                            $duration = $this->parseDuration($videoItem['contentDetails']['duration'] ?? '');
                            $durationMap[$videoItem['id']] = $duration;
                        }
                        
                        // Map durations to videos
                        foreach ($videos as &$video) {
                            if (isset($durationMap[$video['video_id']])) {
                                $video['duration'] = $durationMap[$video['video_id']];
                            }
                        }
                        unset($video); // Break reference
                    }
                } catch (\Exception $e) {
                    // Log but don't fail - duration is optional
                    \Log::warning('Failed to fetch video durations for channel uploads', [
                        'error' => $e->getMessage(),
                        'playlist_id' => $uploadsPlaylistId,
                    ]);
                }
            }

            // Shorts are excluded by using the UULF (long-form) playlist — see formatChannelInfo()
            return [
                'items' => $videos,
                'nextPageToken' => $data['nextPageToken'] ?? null,
                'totalResults' => $data['pageInfo']['totalResults'] ?? 0,
            ];
        } catch (\Exception $e) {
            \Log::error('YouTube API error fetching channel uploads', [
                'error' => $e->getMessage(),
                'playlist_id' => $uploadsPlaylistId,
            ]);
            throw $e;
        }
    }

    /**
     * Fetch playlists created by a channel (paginated)
     */
    public function fetchChannelPlaylists(string $channelId, ?string $pageToken = null): array
    {
        try {
            $url = "https://www.googleapis.com/youtube/v3/playlists";
            $response = Http::timeout(10)->get($url, [
                'key' => $this->apiKey,
                'channelId' => $channelId,
                'part' => 'snippet,contentDetails',
                'maxResults' => 50,
                'pageToken' => $pageToken ?? ''
            ]);

            if (!$response->successful()) {
                \Log::warning('YouTube API channel playlists request failed', [
                    'status' => $response->status(),
                    'channel_id' => $channelId,
                ]);
                throw new Exception("Failed to fetch channel playlists.");
            }

            $data = $response->json();
            
            if (isset($data['error'])) {
                throw new Exception("YouTube API Error: " . ($data['error']['message'] ?? 'Unknown error'));
            }

            $playlists = [];
            foreach ($data['items'] ?? [] as $item) {
                $snippet = $item['snippet'];
                $thumbnails = $snippet['thumbnails'];
                
                $playlists[] = [
                    'playlist_id' => $item['id'],
                    'title' => $snippet['title'],
                    'thumbnail_url' => $thumbnails['default']['url'] ?? $thumbnails['medium']['url'] ?? '',
                    'video_count' => $item['contentDetails']['itemCount'] ?? 0,
                    'type' => 'playlist'
                ];
            }

            return [
                'items' => $playlists,
                'nextPageToken' => $data['nextPageToken'] ?? null,
                'totalResults' => $data['pageInfo']['totalResults'] ?? 0,
            ];
        } catch (\Exception $e) {
            \Log::error('YouTube API error fetching channel playlists', [
                'error' => $e->getMessage(),
                'channel_id' => $channelId,
            ]);
            throw $e;
        }
    }

    /**
     * Get channel information from a video ID
     * Extracts channel info from video metadata (channelId and channelTitle are in snippet)
     * Returns channel_id, channel_name, and channel_thumbnail
     */
    public function getVideoChannelInfo(string $videoId): array
    {
        $videoId = $this->extractVideoId($videoId);
        
        // Cache channel info for 1 hour
        return \Illuminate\Support\Facades\Cache::remember("youtube_video_channel_{$videoId}", 3600, function () use ($videoId) {
            try {
                // Fetch video data to get channel info (snippet contains channelId and channelTitle)
                $url = "https://www.googleapis.com/youtube/v3/videos";
                $response = Http::timeout(10)->get($url, [
                    'key' => $this->apiKey,
                    'id' => $videoId,
                    'part' => 'snippet'
                ]);

                if (!$response->successful() || empty($response->json()['items'])) {
                    return [
                        'channel_id' => null,
                        'channel_name' => null,
                        'channel_thumbnail' => null,
                    ];
                }

                $data = $response->json();
                $snippet = $data['items'][0]['snippet'];
                $channelId = $snippet['channelId'] ?? null;
                $channelName = $snippet['channelTitle'] ?? null;
                
                if (!$channelId) {
                    return [
                        'channel_id' => null,
                        'channel_name' => null,
                        'channel_thumbnail' => null,
                    ];
                }

                // Get channel thumbnail
                $channelThumbnail = $this->getChannelThumbnail($channelId);

                return [
                    'channel_id' => $channelId,
                    'channel_name' => $channelName,
                    'channel_thumbnail' => $channelThumbnail,
                ];
            } catch (\Exception $e) {
                \Log::warning('Failed to get video channel info', [
                    'video_id' => $videoId,
                    'error' => $e->getMessage(),
                ]);
                return [
                    'channel_id' => null,
                    'channel_name' => null,
                    'channel_thumbnail' => null,
                ];
            }
        });
    }

    /**
     * Get channel information from a playlist ID
     * Extracts channel info from playlist metadata (channelId and channelTitle are in snippet)
     * Returns channel_id, channel_name, and channel_thumbnail
     */
    public function getPlaylistChannelInfo(string $playlistId): array
    {
        $playlistId = $this->extractPlaylistId($playlistId) ?? $playlistId;
        
        // Cache channel info for 1 hour
        return \Illuminate\Support\Facades\Cache::remember("youtube_playlist_channel_{$playlistId}", 3600, function () use ($playlistId) {
            try {
                // Fetch playlist metadata which includes channel info
                $url = "https://www.googleapis.com/youtube/v3/playlists";
                $response = Http::timeout(10)->get($url, [
                    'key' => $this->apiKey,
                    'id' => $playlistId,
                    'part' => 'snippet'
                ]);

                if (!$response->successful() || empty($response->json()['items'])) {
                    return [
                        'channel_id' => null,
                        'channel_name' => null,
                        'channel_thumbnail' => null,
                    ];
                }

                $data = $response->json();
                $snippet = $data['items'][0]['snippet'];
                $channelId = $snippet['channelId'] ?? null;
                $channelName = $snippet['channelTitle'] ?? null;
                
                if (!$channelId) {
                    return [
                        'channel_id' => null,
                        'channel_name' => null,
                        'channel_thumbnail' => null,
                    ];
                }

                // Get channel thumbnail
                $channelThumbnail = $this->getChannelThumbnail($channelId);

                return [
                    'channel_id' => $channelId,
                    'channel_name' => $channelName,
                    'channel_thumbnail' => $channelThumbnail,
                ];
            } catch (\Exception $e) {
                \Log::warning('Failed to get playlist channel info', [
                    'playlist_id' => $playlistId,
                    'error' => $e->getMessage(),
                ]);
                return [
                    'channel_id' => null,
                    'channel_name' => null,
                    'channel_thumbnail' => null,
                ];
            }
        });
    }

    /**
     * Get channel thumbnail URL
     * Fetches channel thumbnail from YouTube API (channels.list endpoint)
     * Cached for 24 hours since channel thumbnails rarely change (saves quota)
     */
    public function getChannelThumbnail(string $channelId): ?string
    {
        if (!$channelId) {
            return null;
        }

        // Cache for 24 hours (channel thumbnails rarely change, saves quota)
        return \Illuminate\Support\Facades\Cache::remember("youtube_channel_thumbnail_{$channelId}", 86400, function () use ($channelId) {
            try {
                $url = "https://www.googleapis.com/youtube/v3/channels";
                $response = Http::timeout(10)->get($url, [
                    'key' => $this->apiKey,
                    'id' => $channelId,
                    'part' => 'snippet'
                ]);

                if (!$response->successful() || empty($response->json()['items'])) {
                    return null;
                }

                $data = $response->json();
                $snippet = $data['items'][0]['snippet'];
                $thumbnails = $snippet['thumbnails'] ?? [];
                
                // Prefer default thumbnail, fallback to medium or high
                return $thumbnails['default']['url'] ?? $thumbnails['medium']['url'] ?? $thumbnails['high']['url'] ?? null;
            } catch (\Exception $e) {
                \Log::warning('Failed to get channel thumbnail', [
                    'channel_id' => $channelId,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }
}
