<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

trait InvalidatesUserCache
{
    /**
     * Invalidate cache for a user's gallery and API endpoints.
     * Uses cache versioning to ensure all devices see updates immediately.
     * 
     * Cache versioning automatically invalidates all versioned cache keys:
     * - user_gallery_{$slug}_v{$cacheVersion}
     * - user_videos_{$slug}_{$channelId}_{$contentType}_v{$cacheVersion}
     * - playlist_videos_{$playlistId}_v{$cacheVersion}
     * 
     * Manual cache clearing is only needed for non-versioned keys (e.g., admin_playlist_videos)
     * and should be done in the controller methods that affect specific playlists.
     * 
     * @param int|null $userId The user ID to invalidate cache for
     */
    protected function invalidateUserCache(?int $userId): void
    {
        if ($userId === null) {
            return;
        }
        
        $user = User::find($userId);
        if (!$user) {
            return;
        }
        
        // Update cache version timestamp to force all versioned cache keys to be invalid
        // This ensures all devices see changes immediately on next API call
        // Old cache keys with previous version numbers will no longer match
        $user->update(['cache_version' => now()]);
    }
}

