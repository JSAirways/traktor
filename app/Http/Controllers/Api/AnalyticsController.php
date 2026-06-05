<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceRegistration;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\DeviceRegistrationService;
use App\Services\ViewingSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService,
        protected DeviceRegistrationService $deviceService,
        protected ViewingSessionService $viewingSessionService
    ) {
    }

    /**
     * Track a watch event.
     * Security: Requires valid viewing session for child users, allows parent users to track their own activity.
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'required|string|in:started,paused,resumed,completed,abandoned,position_update',
            'video_id' => 'nullable|integer|exists:videos,id',
            'playlist_id' => 'nullable|integer|exists:playlists,id',
            'position' => 'nullable|integer|min:0',
            'duration' => 'nullable|integer|min:0',
            'completion_percentage' => 'nullable|numeric|min:0|max:100',
            // session_id removed - sessions derived server-side from events
            'slug' => 'required|string',
        ]);

        // Get user and validate access
        $user = $this->getUserForTracking($request, $validated['slug']);
        if (!$user) {
            return response()->error(__('messages.unauthorized_access'), null, 403);
        }

        // Get device registration if available
        $device = $this->deviceService->getDeviceFromCookie($request);

        // Calculate completion percentage if not provided but position and duration are
        if (!isset($validated['completion_percentage']) && isset($validated['position']) && isset($validated['duration']) && $validated['duration'] > 0) {
            $validated['completion_percentage'] = min(100, ($validated['position'] / $validated['duration']) * 100);
        }

        // Track the event (sessions derived server-side from events)
        $this->analyticsService->trackEvent($user, $validated['event_type'], [
            'video_id' => $validated['video_id'] ?? null,
            'playlist_id' => $validated['playlist_id'] ?? null,
            'device_registration_id' => $device?->id,
            'position' => $validated['position'] ?? 0,
            'duration' => $validated['duration'] ?? null,
            'completion_percentage' => $validated['completion_percentage'] ?? null,
        ]);

        return response()->success(__('messages.analytics_event_tracked'));
    }

    /**
     * Start a new watch session (deprecated - sessions now derived from events).
     * Kept for backward compatibility, returns success but does nothing.
     */
    public function startSession(Request $request): JsonResponse
    {
        // Sessions are now derived server-side from events
        // This endpoint is kept for backward compatibility but does nothing
        return response()->success(__('messages.analytics_session_started'), [
            'note' => 'Sessions are now derived automatically from events',
        ]);
    }

    /**
     * End a watch session (deprecated - sessions now derived from events).
     * Kept for backward compatibility, returns success but does nothing.
     */
    public function endSession(Request $request): JsonResponse
    {
        // Sessions are now derived server-side from events
        // This endpoint is kept for backward compatibility but does nothing
        return response()->success(__('messages.analytics_session_ended'), [
            'note' => 'Sessions are now derived automatically from events',
        ]);
    }

    /**
     * Get user for tracking - validates viewing session for child users, allows parent users.
     */
    private function getUserForTracking(Request $request, string $slug): ?User
    {
        // Try to find user by slug
        $user = User::where('slug', $slug)->where('is_viewable', true)->first();
        if (!$user) {
            return null;
        }

        // If authenticated user is the same user or is admin, allow tracking
        if (auth()->check()) {
            $authUser = auth()->user();
            if ($authUser->id === $user->id || $authUser->isAdmin()) {
                return $user;
            }

            // If authenticated user is parent of this user, allow tracking
            if ($user->parent_id === $authUser->id) {
                return $user;
            }
        }

        // For child users, validate viewing session
        if ($user->role === 'user' && $user->parent_id) {
            $isValid = $this->viewingSessionService->validateSession($request, $slug);
            if (!$isValid) {
                return null;
            }
            return $user;
        }

        // For parent users, allow tracking their own activity
        if ($user->role === 'user' && !$user->parent_id) {
            // Parent tracking their own activity - allow if authenticated
            if (auth()->check() && auth()->user()->id === $user->id) {
                return $user;
            }
        }

        return null;
    }
}
