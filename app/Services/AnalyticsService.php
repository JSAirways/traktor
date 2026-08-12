<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoWatchEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AnalyticsService
{
    private const SESSION_GAP_MINUTES = 30;
    private const MATCHING_WINDOW_HOURS = 2;

    /**
     * Get event types that contain position data.
     * 
     * @return array
     */
    private function getPositionEventTypes(): array
    {
        return [
            VideoWatchEvent::EVENT_POSITION_UPDATE,
            VideoWatchEvent::EVENT_COMPLETED,
            VideoWatchEvent::EVENT_PAUSED,
            VideoWatchEvent::EVENT_RESUMED,
            VideoWatchEvent::EVENT_ABANDONED,
        ];
    }

    /**
     * Check if an event has valid position data.
     * 
     * @param VideoWatchEvent $event
     * @return bool
     */
    private function hasValidPositionData(VideoWatchEvent $event): bool
    {
        return $event->position > 0 || 
               ($event->event_type === VideoWatchEvent::EVENT_COMPLETED && $event->duration);
    }

    /**
     * Track a watch event.
     * Sessions are derived server-side from events (grouped by time gaps).
     */
    public function trackEvent(
        User $user,
        string $eventType,
        array $data = []
    ): VideoWatchEvent {
        $event = new VideoWatchEvent([
            'user_id' => $user->id,
            'video_id' => $data['video_id'] ?? null,
            'playlist_id' => $data['playlist_id'] ?? null,
            'device_registration_id' => $data['device_registration_id'] ?? null,
            'event_type' => $eventType,
            'position' => $data['position'] ?? 0,
            'duration' => $data['duration'] ?? null,
            'completion_percentage' => $data['completion_percentage'] ?? null,
            'session_id' => null, // Sessions derived from events, not stored explicitly
        ]);

        $event->created_at = now();
        $event->save();

        return $event;
    }

    /**
     * Calculate watch time from events.
     * Watch time is calculated as the sum of maximum positions reached per video.
     * Position updates contain absolute positions, not deltas, so we use MAX per video.
     * Includes paused, resumed, abandoned, and completed events that have position data.
     * 
     * @param Collection $events Collection of VideoWatchEvent models
     * @return int Total watch time in seconds
     */
    private function calculateWatchTimeFromEvents(Collection $events): int
    {
        // Filter to events that contain position information
        $positionEventTypes = $this->getPositionEventTypes();
        $positionEvents = $events->filter(function ($e) use ($positionEventTypes) {
            return in_array($e->event_type, $positionEventTypes) 
                && $e->video_id !== null 
                && $this->hasValidPositionData($e);
        });

        if ($positionEvents->isEmpty()) {
            return 0;
        }

        // Group by video_id and find maximum position reached per video
        $maxPositionPerVideo = $positionEvents->groupBy('video_id')->map(function ($videoEvents) {
            return $this->getMaxPositionFromEvents($videoEvents);
        });

        // Sum the maximum positions (each video contributes its max watch time)
        return (int) $maxPositionPerVideo->sum();
    }

    /**
     * Get the maximum position from a collection of events.
     * For completed events, prefers position, falls back to duration if position is 0.
     * 
     * @param Collection $events Collection of VideoWatchEvent models
     * @return int Maximum position in seconds
     */
    private function getMaxPositionFromEvents(Collection $events): int
    {
        return $events->max(function ($e) {
            // For completed events, prefer position, fallback to duration if position is 0
            if ($e->event_type === VideoWatchEvent::EVENT_COMPLETED && $e->position == 0 && $e->duration) {
                return $e->duration;
            }
            // For other events, use position if available
            return $e->position ?? 0;
        });
    }

    /**
     * Derive sessions from events by grouping events within time gaps.
     * Events within 30 minutes of each other belong to the same session.
     */
    private function deriveSessionsFromEvents(User $user, $startDate, $endDate): Collection
    {
        // Get all events in the period
        $events = VideoWatchEvent::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'asc')
            ->get();

        if ($events->isEmpty()) {
            return collect([]);
        }

        $sessions = [];
        $currentSession = null;
        $lastEventTime = null;

        foreach ($events as $event) {
            $eventTime = $event->created_at;

            // Calculate gap from last event (use absolute value to handle any ordering issues)
            $gapMinutes = $lastEventTime ? abs($eventTime->diffInMinutes($lastEventTime)) : null;

            // Start new session if:
            // 1. No current session
            // 2. Gap between events is > session gap threshold
            if ($currentSession === null || 
                ($gapMinutes !== null && $gapMinutes > self::SESSION_GAP_MINUTES)) {
                
                // End previous session if exists
                if ($currentSession !== null) {
                    $currentSession['ended_at'] = $lastEventTime;
                    $sessions[] = $currentSession;
                }

                // Start new session
                $currentSession = [
                    'started_at' => $eventTime,
                    'ended_at' => null,
                    'events' => [],
                    'device_registration_id' => $event->device_registration_id,
                ];
            }

            $currentSession['events'][] = $event;
            $lastEventTime = $eventTime;
        }

        // Close last session
        if ($currentSession !== null) {
            $currentSession['ended_at'] = $lastEventTime ?? now();
            $sessions[] = $currentSession;
        }

        // Calculate session stats
        return collect($sessions)->map(function ($session) {
            $events = collect($session['events']);
            
            // Calculate watch time using helper method (MAX position per video, not SUM)
            $watchTime = $this->calculateWatchTimeFromEvents($events);

            // Count unique videos watched (from started events)
            $videosWatched = $events
                ->where('event_type', VideoWatchEvent::EVENT_STARTED)
                ->whereNotNull('video_id')
                ->pluck('video_id')
                ->unique()
                ->count();

            return [
                'started_at' => $session['started_at'],
                'ended_at' => $session['ended_at'],
                'total_watch_time' => $watchTime,
                'videos_watched' => $videosWatched,
                'device_registration_id' => $session['device_registration_id'],
            ];
        });
    }

    /**
     * Get full dashboard analytics for a date range.
     */
    public function getDashboardData(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $rangeStart = $startDate->copy()->startOfDay();
        $rangeEnd = $endDate->copy()->endOfDay();
        $granularity = $this->resolveGranularity($rangeStart, $rangeEnd);

        $rangeEvents = VideoWatchEvent::where('user_id', $user->id)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->orderBy('created_at', 'asc')
            ->get();

        $sessions = $this->deriveSessionsFromEvents($user, $rangeStart, $rangeEnd);
        // Max position per video across the whole range (not summed per session).
        $watchTime = $this->calculateWatchTimeFromEvents($rangeEvents);
        $sessionCount = $sessions->count();
        $avgSessionLength = $sessionCount > 0
            ? (int) round($sessions->avg(fn ($session) => $session['total_watch_time'] ?? 0))
            : 0;

        $videoStarts = $rangeEvents
            ->where('event_type', VideoWatchEvent::EVENT_STARTED)
            ->whereNotNull('video_id')
            ->count();

        return [
            'range' => [
                'start' => $rangeStart->toDateString(),
                'end' => $rangeEnd->toDateString(),
                'label' => $this->formatRangeLabel($rangeStart, $rangeEnd),
                'granularity' => $granularity,
            ],
            'kpis' => [
                'watch_time' => $watchTime,
                'sessions' => $sessionCount,
                'avg_session_length' => $avgSessionLength,
                'video_starts' => $videoStarts,
            ],
            'watch_time_series' => $this->getWatchTimeSeries($user, $rangeStart, $rangeEnd, $granularity),
            'peak_hours' => $this->getPeakViewingHours($user, $rangeStart, $rangeEnd),
            'day_of_week_patterns' => $this->getDayOfWeekPatterns($user, $rangeStart, $rangeEnd),
            'most_watched_videos' => $this->getMostWatchedVideos($user, $rangeStart, $rangeEnd, 5),
            'top_channels' => $this->getTopChannels($user, $rangeStart, $rangeEnd, 5),
            'rewatch_favorites' => $this->getRewatchFavorites($user, $rangeStart, $rangeEnd, 5),
            'recent_activity' => $this->getRecentActivity($user, $rangeStart, $rangeEnd, 10),
        ];
    }

    /**
     * Get recent activity within a date range.
     */
    public function getRecentActivity(User $user, Carbon $startDate, Carbon $endDate, int $limit = 10): Collection
    {
        return VideoWatchEvent::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('event_type', [
                VideoWatchEvent::EVENT_STARTED,
                VideoWatchEvent::EVENT_COMPLETED,
            ])
            ->whereNotNull('video_id')
            ->with(['video', 'playlist', 'deviceRegistration'])
            ->orderBy('created_at', 'desc')
            ->limit($limit * 3)
            ->get()
            ->filter(function ($event) {
                if ($event->video === null) {
                    return false;
                }

                if ($event->event_type === VideoWatchEvent::EVENT_STARTED) {
                    return VideoWatchEvent::where('user_id', $event->user_id)
                        ->where('video_id', $event->video_id)
                        ->where('created_at', '>=', $event->created_at)
                        ->where('created_at', '<=', $event->created_at->copy()->addHours(self::MATCHING_WINDOW_HOURS))
                        ->whereIn('event_type', $this->getPositionEventTypes())
                        ->where(function ($q) {
                            $q->where('position', '>', 0)
                              ->orWhere(function ($q2) {
                                  $q2->where('event_type', VideoWatchEvent::EVENT_COMPLETED)
                                     ->whereNotNull('duration');
                              });
                        })
                        ->exists();
                }

                return true;
            })
            ->take($limit)
            ->values();
    }

    /**
     * Get top channels by watch time.
     */
    public function getTopChannels(User $user, Carbon $startDate, Carbon $endDate, int $limit = 5): Collection
    {
        $channelStats = VideoWatchEvent::where('video_watch_events.user_id', $user->id)
            ->whereBetween('video_watch_events.created_at', [$startDate, $endDate])
            ->where('video_watch_events.event_type', VideoWatchEvent::EVENT_STARTED)
            ->whereNotNull('video_watch_events.video_id')
            ->join('videos', 'video_watch_events.video_id', '=', 'videos.id')
            ->select('videos.channel_id', 'videos.channel_name')
            ->selectRaw('COUNT(*) as watch_count')
            ->groupBy('videos.channel_id', 'videos.channel_name')
            ->get();

        $positionEvents = VideoWatchEvent::where('video_watch_events.user_id', $user->id)
            ->whereBetween('video_watch_events.created_at', [$startDate, $endDate])
            ->whereIn('video_watch_events.event_type', $this->getPositionEventTypes())
            ->whereNotNull('video_watch_events.video_id')
            ->with('video')
            ->get();

        $maxPositionPerVideo = $positionEvents
            ->filter(fn ($e) => $this->hasValidPositionData($e))
            ->groupBy('video_id')
            ->map(fn ($videoEvents) => $this->getMaxPositionFromEvents($videoEvents));

        $videos = Video::whereIn('id', $maxPositionPerVideo->keys())->get()->keyBy('id');

        $channelWatchTimes = collect();
        foreach ($maxPositionPerVideo as $videoId => $maxPosition) {
            $video = $videos->get($videoId);
            if (!$video) {
                continue;
            }

            $key = ($video->channel_id ?? 'unknown') . '|' . ($video->channel_name ?? 'Unknown Channel');
            $channelWatchTimes->put($key, ($channelWatchTimes->get($key) ?? 0) + $maxPosition);
        }

        return $channelStats->map(function ($item) use ($channelWatchTimes) {
            $key = ($item->channel_id ?? 'unknown') . '|' . ($item->channel_name ?? 'Unknown Channel');

            return [
                'channel_id' => $item->channel_id ?? 'unknown',
                'channel_name' => $item->channel_name ?? 'Unknown Channel',
                'watch_count' => (int) $item->watch_count,
                'watch_time' => (int) ($channelWatchTimes->get($key) ?? 0),
            ];
        })->sortByDesc('watch_time')->take($limit)->values();
    }

    /**
     * Get most watched videos.
     */
    public function getMostWatchedVideos(User $user, Carbon $startDate, Carbon $endDate, int $limit = 5): Collection
    {
        $videoStats = VideoWatchEvent::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('event_type', VideoWatchEvent::EVENT_STARTED)
            ->whereNotNull('video_id')
            ->with('video')
            ->select('video_id')
            ->selectRaw('COUNT(*) as watch_count')
            ->selectRaw('MAX(created_at) as last_watched_at')
            ->groupBy('video_id')
            ->get();

        $allPositionEvents = VideoWatchEvent::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('event_type', $this->getPositionEventTypes())
            ->whereNotNull('video_id')
            ->get();

        $videoWatchTimes = $allPositionEvents
            ->groupBy('video_id')
            ->map(function ($events) {
                $validEvents = $events->filter(fn ($e) => $this->hasValidPositionData($e));

                if ($validEvents->isEmpty()) {
                    return 0;
                }

                return $this->getMaxPositionFromEvents($validEvents);
            });

        return $videoStats
            ->filter(fn ($item) => $item->video !== null)
            ->map(function ($item) use ($videoWatchTimes) {
                return [
                    'video' => $item->video,
                    'watch_count' => (int) $item->watch_count,
                    'total_watch_time' => (int) ($videoWatchTimes->get($item->video_id) ?? 0),
                    'last_watched_at' => $item->last_watched_at,
                ];
            })
            ->sortBy([
                ['watch_count', 'desc'],
                ['last_watched_at', 'desc'],
            ])
            ->take($limit)
            ->values();
    }

    /**
     * Get re-watch favorites (videos watched 5+ times).
     */
    public function getRewatchFavorites(User $user, Carbon $startDate, Carbon $endDate, int $limit = 5): Collection
    {
        $favorites = VideoWatchEvent::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('event_type', VideoWatchEvent::EVENT_STARTED)
            ->whereNotNull('video_id')
            ->with('video')
            ->select('video_id')
            ->selectRaw('COUNT(*) as watch_count')
            ->groupBy('video_id')
            ->havingRaw('COUNT(*) >= 5')
            ->orderByDesc('watch_count')
            ->limit($limit)
            ->get()
            ->filter(fn ($item) => $item->video !== null);

        $videoIds = $favorites->pluck('video_id');
        $positionEvents = VideoWatchEvent::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('event_type', $this->getPositionEventTypes())
            ->whereIn('video_id', $videoIds)
            ->get();

        $videoWatchTimes = $positionEvents
            ->groupBy('video_id')
            ->map(function ($events) {
                $validEvents = $events->filter(fn ($e) => $this->hasValidPositionData($e));

                return $validEvents->isEmpty() ? 0 : $this->getMaxPositionFromEvents($validEvents);
            });

        return $favorites->map(function ($item) use ($videoWatchTimes) {
            return [
                'video' => $item->video,
                'watch_count' => (int) $item->watch_count,
                'total_watch_time' => (int) ($videoWatchTimes->get($item->video_id) ?? 0),
            ];
        })->values();
    }

    /**
     * Build watch-time series for the selected range and granularity.
     */
    private function getWatchTimeSeries(User $user, Carbon $startDate, Carbon $endDate, string $granularity): array
    {
        return match ($granularity) {
            'weekly' => $this->getWeeklyBucketWatchTimeData($user, $startDate, $endDate),
            'monthly' => $this->getMonthlyBucketWatchTimeData($user, $startDate, $endDate),
            default => $this->getDailyWatchTimeData($user, $startDate, $endDate),
        };
    }

    /**
     * Daily watch time buckets.
     */
    private function getDailyWatchTimeData(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $currentDate = $startDate->copy()->startOfDay();

        while ($currentDate->lte($endDate)) {
            $dayEnd = $currentDate->copy()->endOfDay();
            if ($dayEnd->gt($endDate)) {
                $dayEnd = $endDate->copy();
            }

            $dayEvents = VideoWatchEvent::where('user_id', $user->id)
                ->whereBetween('created_at', [$currentDate, $dayEnd])
                ->whereIn('event_type', $this->getPositionEventTypes())
                ->whereNotNull('video_id')
                ->get();

            $data[] = [
                'date' => $currentDate->format('Y-m-d'),
                'label' => $currentDate->format('M j'),
                'watch_time' => $this->calculateWatchTimeFromEvents($dayEvents),
            ];

            $currentDate->addDay();
        }

        return $data;
    }

    /**
     * Weekly watch time buckets (Mon–Sun within range).
     */
    private function getWeeklyBucketWatchTimeData(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $currentDate = $startDate->copy()->startOfWeek();

        while ($currentDate->lte($endDate)) {
            $weekStart = $currentDate->greaterThan($startDate) ? $currentDate->copy() : $startDate->copy();
            $weekEnd = $currentDate->copy()->endOfWeek();
            if ($weekEnd->gt($endDate)) {
                $weekEnd = $endDate->copy();
            }

            $weekEvents = VideoWatchEvent::where('user_id', $user->id)
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->whereIn('event_type', $this->getPositionEventTypes())
                ->whereNotNull('video_id')
                ->get();

            $data[] = [
                'date' => $weekStart->format('Y-m-d'),
                'label' => $weekStart->format('M j'),
                'watch_time' => $this->calculateWatchTimeFromEvents($weekEvents),
            ];

            $currentDate->addWeek();
        }

        return $data;
    }

    /**
     * Monthly watch time buckets.
     */
    private function getMonthlyBucketWatchTimeData(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $data = [];
        $currentDate = $startDate->copy()->startOfMonth();

        while ($currentDate->lte($endDate)) {
            $monthStart = $currentDate->greaterThan($startDate) ? $currentDate->copy() : $startDate->copy();
            $monthEnd = $currentDate->copy()->endOfMonth();
            if ($monthEnd->gt($endDate)) {
                $monthEnd = $endDate->copy();
            }

            $monthEvents = VideoWatchEvent::where('user_id', $user->id)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->whereIn('event_type', $this->getPositionEventTypes())
                ->whereNotNull('video_id')
                ->get();

            $data[] = [
                'date' => $monthStart->format('Y-m'),
                'label' => $monthStart->format('M Y'),
                'watch_time' => $this->calculateWatchTimeFromEvents($monthEvents),
            ];

            $currentDate->addMonth()->startOfMonth();
        }

        return $data;
    }

    /**
     * Get peak viewing hours (hourly distribution of started events).
     */
    private function getPeakViewingHours(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $events = VideoWatchEvent::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('event_type', VideoWatchEvent::EVENT_STARTED)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $hourlyData = array_fill(0, 24, 0);
        foreach ($events as $event) {
            $hour = (int) ($event->hour ?? 0);
            if ($hour >= 0 && $hour < 24) {
                $hourlyData[$hour] = (int) ($event->count ?? 0);
            }
        }

        return $hourlyData;
    }

    /**
     * Get day of week patterns (watch time per weekday).
     */
    private function getDayOfWeekPatterns(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $dayPatterns = [
            'Monday' => 0,
            'Tuesday' => 0,
            'Wednesday' => 0,
            'Thursday' => 0,
            'Friday' => 0,
            'Saturday' => 0,
            'Sunday' => 0,
        ];

        $events = VideoWatchEvent::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('event_type', $this->getPositionEventTypes())
            ->whereNotNull('video_id')
            ->get();

        $eventsByDay = $events->groupBy(fn ($event) => $event->created_at->format('l'));

        foreach ($eventsByDay as $dayName => $dayEvents) {
            if (isset($dayPatterns[$dayName])) {
                $dayPatterns[$dayName] += $this->calculateWatchTimeFromEvents($dayEvents);
            }
        }

        return $dayPatterns;
    }

    /**
     * Resolve chart granularity from range length.
     */
    private function resolveGranularity(Carbon $startDate, Carbon $endDate): string
    {
        $days = $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()) + 1;

        if ($days <= 31) {
            return 'daily';
        }

        if ($days <= 90) {
            return 'weekly';
        }

        return 'monthly';
    }

    /**
     * Human-readable range label.
     */
    private function formatRangeLabel(Carbon $startDate, Carbon $endDate): string
    {
        if ($startDate->isSameDay($endDate)) {
            return $startDate->format('M j, Y');
        }

        if ($startDate->year === $endDate->year) {
            return $startDate->format('M j') . ' – ' . $endDate->format('M j, Y');
        }

        return $startDate->format('M j, Y') . ' – ' . $endDate->format('M j, Y');
    }
}

