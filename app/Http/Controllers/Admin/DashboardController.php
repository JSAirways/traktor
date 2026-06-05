<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {
    }

    /**
     * Show dashboard for current user (parent) or selected child.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $selectedSlug = $request->get('slug');
        
        // Get user to display analytics for
        $displayUser = $this->getDisplayUser($user, $selectedSlug);
        
        if (!$displayUser) {
            return redirect()->route('admin.dashboard')
                ->with('error', __('messages.user_not_found'));
        }

        // Get available users for selector (children for parents, all users for admins)
        $availableUsers = $this->getAvailableUsers($user);

        return view('admin.dashboard.index', [
            'user' => $user,
            'displayUser' => $displayUser,
            'availableUsers' => $availableUsers,
        ]);
    }

    /**
     * Show dashboard for a specific user (admin only).
     * Authorization: Only admins can view other users' dashboards.
     * Parents can view their own and their children's dashboards via index() method.
     */
    public function showUser(User $user)
    {
        $authUser = auth()->user();
        
        // Only admins can view other users' dashboards
        if (!$authUser->isAdmin()) {
            abort(403);
        }

        // Get children if this is a parent
        $children = $user->children()->where('is_viewable', true)->get();

        return view('admin.dashboard.user', [
            'authUser' => $authUser,
            'user' => $user,
            'children' => $children,
        ]);
    }

    /**
     * Get user list for admin dashboard.
     * Authorization: Only admins can view the user list.
     */
    public function users()
    {
        $authUser = auth()->user();
        
        // Only admins can view user list
        if (!$authUser->isAdmin()) {
            abort(403);
        }

        // Get all users grouped by parent
        $parents = User::where('role', 'user')
            ->whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->where('is_viewable', true);
            }])
            ->orderBy('username')
            ->get();

        return view('admin.dashboard.users', [
            'parents' => $parents,
        ]);
    }

    /**
     * Get activity overview data (AJAX).
     */
    public function getActivityData(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $selectedSlug = $request->get('slug');
            
            $displayUser = $this->getDisplayUser($user, $selectedSlug);
            
            if (!$displayUser) {
                return response()->error(__('messages.user_not_found'), null, 404);
            }

            // Get period and offset parameters
            $period = $request->get('period', 'week');
            $offset = (int) $request->get('offset', 0);

            // Validate period
            if (!in_array($period, ['week', 'month', 'year'], true)) {
                $period = 'week';
            }

            // Validate offset (limit to reasonable range: -100 to 0)
            $offset = max(-100, min(0, $offset));

            $activityData = $this->analyticsService->getActivityOverview($displayUser, $period, $offset);
            $recentActivity = $this->analyticsService->getRecentActivity($displayUser, 10);
            $sessionStats = $this->analyticsService->getSessionStats($displayUser);

            // Determine if there's previous/next period available
            // For now, we'll allow navigation back up to 90 days (retention period)
            // Offset 0 means current period, so we can't go forward
            $hasPrevious = true; // Can always go back (limited by retention)
            $hasNext = $offset < 0; // Can go forward if not at current period

            return response()->success(__('messages.analytics_data_loaded'), [
                'activity' => $activityData,
                'recent_activity' => $recentActivity->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'event_type' => $event->event_type,
                        'created_at' => $event->created_at->toIso8601String(),
                        'video' => $event->video ? [
                            'id' => $event->video->id,
                            'title' => $event->video->title,
                            'thumbnail_url' => $event->video->thumbnail_url,
                        ] : null,
                        'playlist' => $event->playlist ? [
                            'id' => $event->playlist->id,
                            'title' => $event->playlist->title,
                        ] : null,
                        'device_name' => $event->deviceRegistration?->device_name ?? null,
                    ];
                }),
                'session_stats' => $sessionStats,
                'period_metadata' => [
                    'has_previous' => $hasPrevious,
                    'has_next' => $hasNext,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Dashboard activity data error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->error('Failed to load activity data: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get content insights data (AJAX).
     */
    public function getContentData(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $selectedSlug = $request->get('slug');
            
            $displayUser = $this->getDisplayUser($user, $selectedSlug);
            
            if (!$displayUser) {
                return response()->error(__('messages.user_not_found'), null, 404);
            }

            $contentData = $this->analyticsService->getContentInsights($displayUser);

            return response()->success(__('messages.analytics_data_loaded'), [
                'most_watched_videos' => $contentData['most_watched_videos']->map(function ($item) {
                    $video = $item['video'] ?? null;
                    return [
                        'video' => $video ? [
                            'id' => $video->id,
                            'title' => $video->title,
                            'thumbnail_url' => $video->thumbnail_url,
                            'duration' => $video->duration,
                        ] : null,
                        'watch_count' => $item['watch_count'] ?? 0,
                        'total_watch_time' => $item['total_watch_time'] ?? 0,
                        'avg_completion' => $item['avg_completion'] ?? 0,
                    ];
                }),
                'top_channels' => $contentData['top_channels'],
                'most_watched_playlists' => $contentData['most_watched_playlists']->map(function ($item) {
                    $playlist = $item['playlist'] ?? null;
                    return [
                        'playlist' => $playlist ? [
                            'id' => $playlist->id,
                            'title' => $playlist->title,
                            'thumbnail_url' => $playlist->thumbnail_url,
                        ] : null,
                        'videos_watched' => $item['videos_watched'] ?? 0,
                        'total_starts' => $item['total_starts'] ?? 0,
                        'avg_videos_per_session' => $item['avg_videos_per_session'] ?? 0,
                    ];
                }),
                'rewatch_favorites' => $contentData['rewatch_favorites']->map(function ($item) {
                    $video = $item['video'] ?? null;
                    return [
                        'video' => $video ? [
                            'id' => $video->id,
                            'title' => $video->title,
                            'thumbnail_url' => $video->thumbnail_url,
                        ] : null,
                        'watch_count' => $item['watch_count'] ?? 0,
                    ];
                }),
                'completion_rates' => $contentData['completion_rates'],
            ]);
        } catch (\Exception $e) {
            \Log::error('Dashboard content data error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->error('Failed to load content data: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get user to display analytics for.
     */
    private function getDisplayUser(User $authUser, ?string $selectedSlug): ?User
    {
        // If slug provided, find that user
        if ($selectedSlug) {
            $selectedUser = User::where('slug', $selectedSlug)
                ->where('is_viewable', true)
                ->first();
            
            if (!$selectedUser) {
                return null;
            }

            // Check if auth user can view this user's analytics
            if ($authUser->isAdmin() || $authUser->id === $selectedUser->id || $selectedUser->parent_id === $authUser->id) {
                return $selectedUser;
            }

            return null;
        }

        // No slug provided - show auth user's own analytics
        return $authUser;
    }

    /**
     * Get available users for selector.
     */
    private function getAvailableUsers(User $authUser): array
    {
        if ($authUser->isAdmin()) {
            // Admins see all users
            return User::where('is_viewable', true)
                ->orderBy('username')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'slug' => $user->slug,
                        'username' => $user->username,
                        'role' => $user->role,
                        'parent_id' => $user->parent_id,
                    ];
                })
                ->toArray();
        }

        // Parents see themselves and their children
        $users = collect([$authUser]);
        $users = $users->merge($authUser->children()->where('is_viewable', true)->get());

        return $users->map(function ($user) {
            return [
                'id' => $user->id,
                'slug' => $user->slug,
                'username' => $user->username,
                'role' => $user->role,
                'parent_id' => $user->parent_id,
            ];
        })->toArray();
    }
}
