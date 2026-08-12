<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    private const MAX_RANGE_DAYS = 365;

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

        $displayUser = $this->getDisplayUser($user, $selectedSlug);

        if (!$displayUser) {
            return redirect()->route('admin.dashboard')
                ->with('error', __('messages.user_not_found'));
        }

        $availableUsers = $this->getAvailableUsers($user);

        return view('admin.dashboard.index', [
            'user' => $user,
            'displayUser' => $displayUser,
            'availableUsers' => $availableUsers,
        ]);
    }

    /**
     * Show dashboard for a specific user (admin only).
     */
    public function showUser(User $user)
    {
        $authUser = auth()->user();

        if (!$authUser->isAdmin()) {
            abort(403);
        }

        $children = $user->children()->where('is_viewable', true)->get();

        return view('admin.dashboard.user', [
            'authUser' => $authUser,
            'user' => $user,
            'displayUser' => $user,
            'availableUsers' => collect([$user]),
            'children' => $children,
        ]);
    }

    /**
     * Get user list for admin dashboard.
     */
    public function users()
    {
        $authUser = auth()->user();

        if (!$authUser->isAdmin()) {
            abort(403);
        }

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
     * Get unified dashboard analytics data (AJAX).
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $selectedSlug = $request->get('slug');

            $displayUser = $this->getDisplayUser($user, $selectedSlug);

            if (!$displayUser) {
                return response()->error(__('messages.user_not_found'), null, 404);
            }

            [$startDate, $endDate] = $this->resolveDateRange($request);
            $dashboard = $this->analyticsService->getDashboardData($displayUser, $startDate, $endDate);

            return response()->success(__('messages.analytics_data_loaded'), [
                'range' => $dashboard['range'],
                'kpis' => $dashboard['kpis'],
                'watch_time_series' => $dashboard['watch_time_series'],
                'peak_hours' => $dashboard['peak_hours'],
                'day_of_week_patterns' => $dashboard['day_of_week_patterns'],
                'most_watched_videos' => $dashboard['most_watched_videos']->map(function ($item) {
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
                    ];
                }),
                'top_channels' => $dashboard['top_channels'],
                'rewatch_favorites' => $dashboard['rewatch_favorites']->map(function ($item) {
                    $video = $item['video'] ?? null;

                    return [
                        'video' => $video ? [
                            'id' => $video->id,
                            'title' => $video->title,
                            'thumbnail_url' => $video->thumbnail_url,
                        ] : null,
                        'watch_count' => $item['watch_count'] ?? 0,
                        'total_watch_time' => $item['total_watch_time'] ?? 0,
                    ];
                }),
                'recent_activity' => $dashboard['recent_activity']->map(function ($event) {
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
            ]);
        } catch (ValidationException $e) {
            return response()->error($e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            \Log::error('Dashboard data error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->error('Failed to load dashboard data: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Resolve and validate start/end date range from the request.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        $defaultEnd = now()->startOfDay();
        $defaultStart = $defaultEnd->copy()->subDays(27);

        $startInput = $request->get('start');
        $endInput = $request->get('end');

        try {
            $startDate = $startInput
                ? Carbon::createFromFormat('Y-m-d', $startInput)->startOfDay()
                : $defaultStart;
            $endDate = $endInput
                ? Carbon::createFromFormat('Y-m-d', $endInput)->startOfDay()
                : $defaultEnd;
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'start' => ['Invalid date format. Use Y-m-d.'],
            ]);
        }

        $endWasClamped = false;
        if ($endDate->gt(now()->startOfDay())) {
            $endDate = now()->startOfDay();
            $endWasClamped = true;
        }

        if ($startDate->gt($endDate)) {
            // Future ranges can invert after clamping end to today; collapse to today.
            if ($endWasClamped) {
                $startDate = $endDate->copy();
            } else {
                throw ValidationException::withMessages([
                    'start' => ['Start date must be on or before end date.'],
                ]);
            }
        }

        if ($startDate->diffInDays($endDate) + 1 > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'start' => ['Date range cannot exceed ' . self::MAX_RANGE_DAYS . ' days.'],
            ]);
        }

        return [$startDate, $endDate];
    }

    /**
     * Get user to display analytics for.
     */
    private function getDisplayUser(User $authUser, ?string $selectedSlug): ?User
    {
        if ($selectedSlug) {
            $selectedUser = User::where('slug', $selectedSlug)
                ->where('is_viewable', true)
                ->first();

            if (!$selectedUser) {
                return null;
            }

            if ($authUser->isAdmin() || $authUser->id === $selectedUser->id || $selectedUser->parent_id === $authUser->id) {
                return $selectedUser;
            }

            return null;
        }

        return $authUser;
    }

    /**
     * Get available users for selector.
     */
    private function getAvailableUsers(User $authUser)
    {
        if ($authUser->isAdmin()) {
            return User::where('is_viewable', true)
                ->orderBy('username')
                ->get();
        }

        return collect([$authUser])
            ->merge($authUser->children()->where('is_viewable', true)->orderBy('username')->get())
            ->values();
    }
}
