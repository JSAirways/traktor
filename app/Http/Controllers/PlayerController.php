<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\User;
use App\Models\Video;
use App\Services\AssetService;
use App\Services\DeviceRegistrationService;
use App\Services\UserLookupService;
use App\Services\ViewingSessionService;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function __construct(
        protected DeviceRegistrationService $deviceService,
        protected UserLookupService $userLookupService,
        protected AssetService $assetService,
        protected ViewingSessionService $viewingSessionService
    ) {
    }

    /**
     * Display single video player page.
     */
    public function show(Request $request, string $slug, string $videoId)
    {
        // Use ViewingSessionService consistently (same as GalleryController)
        // If user is viewing gallery/playlist, they've already entered PIN, so session should exist
        [$isValid, $user, $redirectRoute] = $this->viewingSessionService->getOrCreateSession($request, $slug, true);
        
        if (!$isValid || !$user) {
            // Session is missing - redirect to welcome page
            // PIN entry should only happen on profile selection page, not here
            return redirect()->route('welcome')
                ->with('error', __('messages.session_expired_enter_pin'));
        }

        // Find video
        $video = Video::where('video_id', $videoId)
            ->where('user_id', $user->id)
            ->where('is_visible', true)
            ->whereNull('playlist_id')
            ->firstOrFail();

        // Get channel ID from query param (for back navigation)
        $channelId = $request->query('channel', 'all');

        // Get cat GIFs for pause overlay
        $catGifs = $this->assetService->getCatGifsFromAssets();

        // Render player page directly
        return view('player.show', [
            'user' => $user,
            'video' => $video,
            'videoId' => $video->video_id,
            'channelId' => $channelId,
            'catGifs' => $catGifs,
        ]);
    }

    /**
     * Display playlist player page.
     */
    public function showPlaylist(Request $request, string $slug, int $playlistId)
    {
        // Use ViewingSessionService consistently (same as GalleryController)
        // If user is viewing gallery/playlist, they've already entered PIN, so session should exist
        [$isValid, $user, $redirectRoute] = $this->viewingSessionService->getOrCreateSession($request, $slug, true);
        
        if (!$isValid || !$user) {
            // Session is missing - redirect to welcome page
            // PIN entry should only happen on profile selection page, not here
            return redirect()->route('welcome')
                ->with('error', __('messages.session_expired_enter_pin'));
        }

        // Find playlist
        $playlist = Playlist::where('id', $playlistId)
            ->where('user_id', $user->id)
            ->where('is_visible', true)
            ->firstOrFail();

        // Load videos directly to ensure they're loaded correctly
        // This is more reliable than using the relationship
        $videos = Video::where('playlist_id', $playlist->id)
            ->where('is_visible', true)
            ->select('id', 'video_id', 'title', 'duration', 'display_order', 'is_visible')
            ->orderBy('display_order')
            ->get();

        // Get current video index from query param (default: 0)
        $currentIndex = max(0, (int) $request->query('index', 0));
        
        // Debug: Log if videos collection is empty
        if ($videos->isEmpty()) {
            \Log::warning('PlayerController: Playlist has no videos', [
                'playlist_id' => $playlistId,
                'user_id' => $user->id,
                'playlist_title' => $playlist->title,
            ]);
        }
        
        // Ensure index is within bounds
        if ($currentIndex >= $videos->count()) {
            $currentIndex = 0;
        }

        // Get channel ID from query param (for back navigation)
        $channelId = $request->query('channel', 'all');

        // Get cat GIFs for pause overlay
        $catGifs = $this->assetService->getCatGifsFromAssets();

        // Prepare playlist data for view
        $playlistData = [
            'videos' => $videos->map(function($video, $index) {
                return [
                    'id' => $video->id,
                    'video_id' => $video->video_id,
                    'title' => $video->title ?? null,
                    'duration' => $video->duration ?? null,
                    'index' => $index,
                ];
            })->toArray(),
        ];
        
        // Debug: Log playlist data being passed to view
        \Log::debug('PlayerController: Playlist data prepared', [
            'playlist_id' => $playlistId,
            'videos_count' => $videos->count(),
            'playlist_data_videos_count' => count($playlistData['videos']),
            'current_index' => $currentIndex,
        ]);

        // Render player page directly
        return view('player.show', [
            'user' => $user,
            'playlist' => $playlist,
            'playlistId' => $playlistId,
            'videos' => $videos,
            'currentIndex' => $currentIndex,
            'videoId' => $videos->count() > 0 ? $videos[$currentIndex]->video_id : null,
            'channelId' => $channelId,
            'catGifs' => $catGifs,
            'playlistData' => $playlistData,
        ]);
    }

    /**
     * Get player HTML fragment for AJAX loading (single video).
     * Returns HTML fragment if Accept header includes text/html, otherwise redirects to full page.
     */
    public function getPlayerHtml(Request $request, string $slug, string $videoId)
    {
        // Verify viewing session matches slug
        $viewingSlug = session('viewing_slug');
        
        if ($viewingSlug !== $slug) {
            if ($request->accepts('text/html')) {
                return response()->error(__('messages.user_not_found'), null, 403);
            }
            return redirect()->route('welcome')
                ->with('error', __('messages.user_not_found'));
        }

        // Find user by slug using UserLookupService
        $user = $this->userLookupService->findViewableUserBySlug($request, $slug);
        
        if (!$user) {
            if ($request->accepts('text/html')) {
                abort(404, __('messages.user_not_found'));
            }
            abort(404, __('messages.user_not_found'));
        }

        // Find video
        $video = Video::where('video_id', $videoId)
            ->where('user_id', $user->id)
            ->where('is_visible', true)
            ->whereNull('playlist_id')
            ->firstOrFail();

        // Get channel ID from query param (for back navigation)
        $channelId = $request->query('channel', 'all');

        // Get cat GIFs for pause overlay
        $catGifs = $this->assetService->getCatGifsFromAssets();

        // Check if client wants HTML fragment
        if ($request->accepts('text/html')) {
            return view('player._partial', [
                'user' => $user,
                'video' => $video,
                'videoId' => $video->video_id,
                'channelId' => $channelId,
                'catGifs' => $catGifs,
            ])->header('Content-Type', 'text/html');
        }

        // Fallback to full page view
        return view('player.show', [
            'user' => $user,
            'video' => $video,
            'videoId' => $video->video_id,
            'channelId' => $channelId,
            'catGifs' => $catGifs,
        ]);
    }

    /**
     * Get playlist player HTML fragment for AJAX loading.
     * Returns HTML fragment if Accept header includes text/html, otherwise redirects to full page.
     */
    public function getPlaylistPlayerHtml(Request $request, string $slug, int $playlistId)
    {
        // Verify viewing session matches slug
        $viewingSlug = session('viewing_slug');
        
        if ($viewingSlug !== $slug) {
            if ($request->accepts('text/html')) {
                return response()->error(__('messages.user_not_found'), null, 403);
            }
            return redirect()->route('welcome')
                ->with('error', __('messages.user_not_found'));
        }

        // Find user by slug using UserLookupService
        $user = $this->userLookupService->findViewableUserBySlug($request, $slug);
        
        if (!$user) {
            if ($request->accepts('text/html')) {
                abort(404, __('messages.user_not_found'));
            }
            abort(404, __('messages.user_not_found'));
        }

        // Find playlist
        $playlist = Playlist::where('id', $playlistId)
            ->where('user_id', $user->id)
            ->where('is_visible', true)
            ->with(['videos' => function ($query) {
                $query->select('id', 'video_id', 'title', 'duration', 'display_order', 'is_visible')
                    ->where('is_visible', true)
                    ->orderBy('display_order');
            }])
            ->firstOrFail();

        // Get current video index from query param (default: 0)
        $currentIndex = max(0, (int) $request->query('index', 0));
        $videos = $playlist->videos;
        
        // Ensure index is within bounds
        if ($currentIndex >= $videos->count()) {
            $currentIndex = 0;
        }

        // Get channel ID from query param (for back navigation)
        $channelId = $request->query('channel', 'all');

        // Get cat GIFs for pause overlay
        $catGifs = $this->assetService->getCatGifsFromAssets();

        // Check if client wants HTML fragment
        if ($request->accepts('text/html')) {
            return view('player._partial', [
                'user' => $user,
                'playlist' => $playlist,
                'playlistId' => $playlistId,
                'videos' => $videos,
                'currentIndex' => $currentIndex,
                'channelId' => $channelId,
                'catGifs' => $catGifs,
            ])->header('Content-Type', 'text/html');
        }

        // Fallback to full page view
        return view('player.show', [
            'user' => $user,
            'playlist' => $playlist,
            'playlistId' => $playlistId,
            'videos' => $videos,
            'currentIndex' => $currentIndex,
            'channelId' => $channelId,
            'catGifs' => $catGifs,
        ]);
    }
}

