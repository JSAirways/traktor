<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeviceRegistration;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoWatchEvent;
use App\Models\WatchSession;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class AnalyticsService
{
    private const DEFAULT_RETENTION_DAYS = 90;
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
     * Get activity overview metrics.
     * 
     * @param User $user
     * @param string $period Period type: 'week', 'month', or 'year'
     * @param int $offset Offset from current period (0 = current, -1 = previous, etc.)
     * @param int $days Retention days for average calculation (default: 90)
     * @return array
     */
    public function getActivityOverview(User $user, string $period = 'week', int $offset = 0, int $days = self::DEFAULT_RETENTION_DAYS): array
    {
        // Calculate period dates
        [$periodStart, $periodEnd] = $this->calculatePeriodDates($period, $offset);
        
        // Get stats for the selected period
        $periodStats = $this->getTimeStatsForPeriod($user, $periodStart, $periodEnd);

        // Today's stats (always current day)
        $todayStart = now()->startOfDay();
        $todayStats = $this->getTimeStatsForPeriod($user, $todayStart, now());

        // Average session length (derived from events within retention period)
        $retentionStart = now()->subDays($days);
        $sessions = $this->deriveSessionsFromEvents($user, $retentionStart, now());
        $avgSessionLength = $sessions->avg(function ($session) {
            return $session['total_watch_time'] ?? 0;
        }) ?? 0;

        // Period watch time data
        $periodData = $this->getPeriodWatchTime($user, $period, $periodStart, $periodEnd);

        // Peak viewing hours (hourly distribution for selected period)
        $peakHours = $this->getPeakViewingHours($user, $periodStart, $periodEnd);

        // Day of week patterns (for selected period)
        $dayOfWeekPatterns = $this->getDayOfWeekPatterns($user, $periodStart, $periodEnd);

        // Calculate period label
        $periodLabel = $this->formatPeriodLabel($period, $periodStart, $periodEnd);

        return [
            'period' => $period,
            'offset' => $offset,
            'period_label' => $periodLabel,
            'period_start' => $periodStart->toIso8601String(),
            'period_end' => $periodEnd->toIso8601String(),
            'today' => [
                'watch_time' => $todayStats['watch_time'],
                'sessions' => $todayStats['sessions'],
            ],
            'period_stats' => [
                'watch_time' => $periodStats['watch_time'],
                'sessions' => $periodStats['sessions'],
            ],
            'average' => [
                'session_length' => (int) round($avgSessionLength),
            ],
            'period_data' => $periodData,
            'peak_hours' => $peakHours,
            'day_of_week_patterns' => $dayOfWeekPatterns,
        ];
    }

    /**
     * Get content insights metrics.
     */
    public function getContentInsights(User $user, int $days = self::DEFAULT_RETENTION_DAYS): array
    {
        $startDate = now()->subDays($days);

        $mostWatchedVideos = $this->getMostWatchedVideos($user, $days, 10);
        $topChannels = $this->getTopChannels($user, $days, 10);
        $mostWatchedPlaylists = $this->getMostWatchedPlaylists($user, $days, 10);
        $rewatchFavorites = $this->getRewatchFavorites($user, $days, 10);
        $completionRates = $this->getCompletionRates($user, $days);

        return [
            'most_watched_videos' => $mostWatchedVideos,
            'top_channels' => $topChannels,
            'most_watched_playlists' => $mostWatchedPlaylists,
            'rewatch_favorites' => $rewatchFavorites,
            'completion_rates' => $completionRates,
        ];
    }

    /**
     * Get recent activity with device names.
     * Uses same date range as other analytics methods for consistency.
     * Filters out events where video relationship failed to load (deleted videos).
     * Only includes events that have corresponding position data to match weekly statistics.
     */
    public function getRecentActivity(User $user, int $limit = 10, int $days = self::DEFAULT_RETENTION_DAYS): Collection
    {
        $startDate = now()->subDays($days);
        
        // Get all started/completed events
        $events = VideoWatchEvent::where('user_id', $user->id)
            ->where('created_at', '>=', $startDate)
            ->whereIn('event_type', [
                VideoWatchEvent::EVENT_STARTED,
                VideoWatchEvent::EVENT_COMPLETED,
            ])
            ->whereNotNull('video_id')
            ->with(['video', 'playlist', 'deviceRegistration'])
            ->orderBy('created_at', 'desc')
            ->limit($limit * 3) // Get more to account for filtering
            ->get()
            ->filter(function ($event) {
                // Filter out events where video was deleted or relationship failed
                if ($event->video === null) {
                    return false;
                }
                
                // For started events, check if there's corresponding position data
                // (to match what weekly statistics show)
                if ($event->event_type === VideoWatchEvent::EVENT_STARTED) {
                    // Check if there are position events for this video within the matching window
                    $hasPositionData = VideoWatchEvent::where('user_id', $event->user_id)
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
                    
                    return $hasPositionData;
                }
                
                // Completed events are always valid
                return true;
            })
            ->take($limit)
            ->values();
        
        return $events;
    }

    /**
     * Get time patterns.
     */
    public function getTimePatterns(User $user, int $days = self::DEFAULT_RETENTION_DAYS): array
    {
        return [
            'hourly_distribution' => $this->getPeakViewingHours($user, $days),
            'day_of_week' => $this->getDayOfWeekPatterns($user, $days),
            'daily_trends' => $this->getDailyWatchTimeTrend($user, $days),
        ];
    }

    /**
     * Get session statistics (derived from events).
     */
    public function getSessionStats(User $user, int $days = self::DEFAULT_RETENTION_DAYS): array
    {
        $startDate = now()->subDays($days);

        // Derive sessions from events
        $sessions = $this->deriveSessionsFromEvents($user, $startDate, now());

        $avgLength = $sessions->avg(function ($session) {
            return $session['total_watch_time'] ?? 0;
        }) ?? 0;
        $avgVideos = $sessions->avg(function ($session) {
            return $session['videos_watched'] ?? 0;
        }) ?? 0;
        $totalSessions = $sessions->count();

        // Session duration distribution
        $distribution = [
            '0-15' => $sessions->filter(fn($s) => $s['total_watch_time'] < 900)->count(),
            '15-30' => $sessions->filter(fn($s) => $s['total_watch_time'] >= 900 && $s['total_watch_time'] < 1800)->count(),
            '30-60' => $sessions->filter(fn($s) => $s['total_watch_time'] >= 1800 && $s['total_watch_time'] < 3600)->count(),
            '60+' => $sessions->filter(fn($s) => $s['total_watch_time'] >= 3600)->count(),
        ];

        return [
            'average_length' => (int) round($avgLength),
            'average_videos' => round($avgVideos, 1),
            'total_sessions' => $totalSessions,
            'duration_distribution' => $distribution,
        ];
    }

    /**
     * Get top channels by watch time.
     */
    public function getTopChannels(User $user, int $days = self::DEFAULT_RETENTION_DAYS, int $limit = 10): Collection
    {
        $startDate = now()->subDays($days);

        // Get watch count from started events
        $channelStats = VideoWatchEvent::where('video_watch_events.user_id', $user->id)
            ->where('video_watch_events.created_at', '>=', $startDate)
            ->where('video_watch_events.event_type', VideoWatchEvent::EVENT_STARTED)
            ->whereNotNull('video_watch_events.video_id')
            ->join('videos', 'video_watch_events.video_id', '=', 'videos.id')
            ->select('videos.channel_id', 'videos.channel_name')
            ->selectRaw('COUNT(*) as watch_count')
            ->groupBy('videos.channel_id', 'videos.channel_name')
            ->get();

        // Calculate actual watch time from all events with position data
        // Use MAX position per video, then group by channel and sum
        $positionEvents = VideoWatchEvent::where('video_watch_events.user_id', $user->id)
            ->where('video_watch_events.created_at', '>=', $startDate)
            ->whereIn('video_watch_events.event_type', $this->getPositionEventTypes())
            ->whereNotNull('video_watch_events.video_id')
            ->with('video')
            ->get();

        // Group by video_id, get max position per video (filter to events with valid position data)
        $maxPositionPerVideo = $positionEvents
            ->filter(function ($e) {
                return $this->hasValidPositionData($e);
            })
            ->groupBy('video_id')
            ->map(function ($videoEvents) {
                return $this->getMaxPositionFromEvents($videoEvents);
            });

        // Get video channel info for each video
        $videoIds = $maxPositionPerVideo->keys();
        $videos = Video::whereIn('id', $videoIds)->get()->keyBy('id');

        // Group by channel and sum max positions
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
            $watchTime = $channelWatchTimes->get($key) ?? 0;
            
            return [
                'channel_id' => $item->channel_id ?? 'unknown',
                'channel_name' => $item->channel_name ?? 'Unknown Channel',
                'watch_count' => (int) $item->watch_count,
                'watch_time' => (int) $watchTime,
            ];
        })->sortByDesc('watch_time')->take($limit)->values();
    }

    /**
     * Get most watched videos.
     */
    public function getMostWatchedVideos(User $user, int $days = self::DEFAULT_RETENTION_DAYS, int $limit = 10): Collection
    {
        $startDate = now()->subDays($days);

        // Get watch count, average completion, and most recent watch time from started events
        $videoStats = VideoWatchEvent::where('user_id', $user->id)
            ->where('created_at', '>=', $startDate)
            ->where('event_type', VideoWatchEvent::EVENT_STARTED)
            ->whereNotNull('video_id')
            ->with('video')
            ->select('video_id')
            ->selectRaw('COUNT(*) as watch_count')
            ->selectRaw('AVG(completion_percentage) as avg_completion')
            ->selectRaw('MAX(created_at) as last_watched_at')
            ->groupBy('video_id')
            ->get();

        // Calculate actual watch time from all events with position data
        // Use MAX position per video (not SUM - position is absolute, not delta)
        $allPositionEvents = VideoWatchEvent::where('user_id', $user->id)
            ->where('created_at', '>=', $startDate)
            ->whereIn('event_type', $this->getPositionEventTypes())
            ->whereNotNull('video_id')
            ->get();
        
        // Group by video and calculate watch time using helper method
        $videoWatchTimes = $allPositionEvents
            ->groupBy('video_id')
            ->map(function ($events) {
                $validEvents = $events->filter(function ($e) {
                    return $this->hasValidPositionData($e);
                });
                
                if ($validEvents->isEmpty()) {
                    return 0;
                }
                
                return $this->getMaxPositionFromEvents($validEvents);
            });

        return $videoStats
            ->filter(function ($item) {
                // Filter out videos where the relationship failed to load (deleted videos)
                return $item->video !== null;
            })
            ->map(function ($item) use ($videoWatchTimes) {
                $watchTime = $videoWatchTimes->get($item->video_id) ?? 0;
                
                return [
                    'video' => $item->video,
                    'watch_count' => (int) $item->watch_count,
                    'total_watch_time' => (int) $watchTime,
                    'avg_completion' => round((float) ($item->avg_completion ?? 0), 1),
                    'last_watched_at' => $item->last_watched_at,
                ];
            })
            ->sortBy([
                ['watch_count', 'desc'],
                ['last_watched_at', 'desc'], // Secondary sort by most recent for ties
            ])
            ->take($limit)
            ->values();
    }

    /**
     * Get most watched playlists.
     */
    public function getMostWatchedPlaylists(User $user, int $days = self::DEFAULT_RETENTION_DAYS, int $limit = 10): Collection
    {
        $startDate = now()->subDays($days);

        return VideoWatchEvent::where('user_id', $user->id)
            ->where('created_at', '>=', $startDate)
            ->where('event_type', VideoWatchEvent::EVENT_STARTED)
            ->whereNotNull('playlist_id')
            ->with('playlist')
            ->select('playlist_id')
            ->selectRaw('COUNT(DISTINCT video_id) as videos_watched')
            ->selectRaw('COUNT(*) as total_starts')
            ->groupBy('playlist_id')
            ->orderByDesc('total_starts')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'playlist' => $item->playlist,
                    'videos_watched' => (int) $item->videos_watched,
                    'total_starts' => (int) $item->total_starts,
                    'avg_videos_per_session' => round($item->total_starts > 0 ? $item->videos_watched / $item->total_starts : 0, 1),
                ];
            });
    }

    /**
     * Get re-watch favorites (videos watched 5+ times).
     */
    public function getRewatchFavorites(User $user, int $days = self::DEFAULT_RETENTION_DAYS, int $limit = 10): Collection
    {
        $startDate = now()->subDays($days);

        return VideoWatchEvent::where('user_id', $user->id)
            ->where('created_at', '>=', $startDate)
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
            ->map(function ($item) {
                return [
                    'video' => $item->video,
                    'watch_count' => (int) $item->watch_count,
                ];
            });
    }

    /**
     * Get completion rates.
     * Calculates based on unique video sessions, not total event counts.
     * A video can be started and completed multiple times, so we need to match them properly.
     */
    public function getCompletionRates(User $user, int $days = self::DEFAULT_RETENTION_DAYS): array
    {
        $startDate = now()->subDays($days);

        // Get all started events with video_id
        $startedEvents = VideoWatchEvent::where('user_id', $user->id)
            ->where('created_at', '>=', $startDate)
            ->where('event_type', VideoWatchEvent::EVENT_STARTED)
            ->whereNotNull('video_id')
            ->orderBy('created_at', 'asc')
            ->get();

        // Get all completed events with video_id
        $completedEvents = VideoWatchEvent::where('user_id', $user->id)
            ->where('created_at', '>=', $startDate)
            ->where('event_type', VideoWatchEvent::EVENT_COMPLETED)
            ->whereNotNull('video_id')
            ->orderBy('created_at', 'asc')
            ->get();

        // Get all abandoned events
        $abandonedEvents = VideoWatchEvent::where('user_id', $user->id)
            ->where('created_at', '>=', $startDate)
            ->where('event_type', VideoWatchEvent::EVENT_ABANDONED)
            ->whereNotNull('video_id')
            ->get();

        // Match started events to completed events first
        $matchResult = $this->matchEvents($startedEvents, $completedEvents, self::MATCHING_WINDOW_HOURS);
        $matchedCompleted = $matchResult['matched'];
        $matchedStartedIds = $matchResult['matchedStartedIds'];
        $usedEventIds = $matchResult['usedEventIds'];

        // Match remaining started events to abandoned events
        $unmatchedStartedEvents = $startedEvents->whereNotIn('id', $matchedStartedIds);
        $matchResult = $this->matchEvents($unmatchedStartedEvents, $abandonedEvents, self::MATCHING_WINDOW_HOURS, $usedEventIds);
        $matchedAbandoned = $matchResult['matched'];
        $matchedStartedIds = array_merge($matchedStartedIds, $matchResult['matchedStartedIds']);

        $totalStarted = $startedEvents->count();
        $fullyWatched = $matchedCompleted->count();
        
        // Partially watched = started events that were matched to abandoned OR unmatched started events
        // (started but not completed and not abandoned)
        $unmatchedStarted = $totalStarted - count($matchedStartedIds);
        $partiallyWatched = $matchedAbandoned->count() + $unmatchedStarted;

        $completionRate = $totalStarted > 0 ? ($fullyWatched / $totalStarted) * 100 : 0;

        return [
            'total_started' => $totalStarted,
            'fully_watched' => $fullyWatched,
            'partially_watched' => $partiallyWatched,
            'completion_rate' => round($completionRate, 1),
        ];
    }

    /**
     * Get time stats for a specific period (derived from events).
     */
    private function getTimeStatsForPeriod(User $user, $startDate, $endDate): array
    {
        // Derive sessions from events
        $sessions = $this->deriveSessionsFromEvents($user, $startDate, $endDate);

        $watchTime = $sessions->sum(function ($session) {
            return $session['total_watch_time'] ?? 0;
        });
        $sessionCount = $sessions->count();

        return [
            'watch_time' => (int) $watchTime,
            'sessions' => $sessionCount,
        ];
    }

    /**
     * Get period watch time data (derived from events).
     * 
     * @param User $user
     * @param string $period Period type: 'week', 'month', or 'year'
     * @param \Carbon\Carbon $periodStart Period start date
     * @param \Carbon\Carbon $periodEnd Period end date
     * @return array
     */
    private function getPeriodWatchTime(User $user, string $period, $periodStart, $periodEnd): array
    {
        return match ($period) {
            'week' => $this->getWeeklyWatchTimeData($user, $periodStart, $periodEnd),
            'month' => $this->getMonthlyWatchTimeData($user, $periodStart, $periodEnd),
            'year' => $this->getYearlyWatchTimeData($user, $periodStart, $periodEnd),
            default => $this->getWeeklyWatchTimeData($user, $periodStart, $periodEnd),
        };
    }

    /**
     * Get weekly watch time data (7 days, derived from events).
     */
    private function getWeeklyWatchTimeData(User $user, $periodStart, $periodEnd): array
    {
        $weeklyData = [];
        $currentDate = $periodStart->copy();
        
        while ($currentDate->lte($periodEnd)) {
            $dayEnd = $currentDate->copy()->endOfDay();

            // Calculate watch time from events for this day
            $dayEvents = VideoWatchEvent::where('user_id', $user->id)
                ->whereBetween('created_at', [$currentDate, $dayEnd])
                ->whereIn('event_type', $this->getPositionEventTypes())
                ->whereNotNull('video_id')
                ->get();
            
            $watchTime = $this->calculateWatchTimeFromEvents($dayEvents);

            $weeklyData[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day' => $currentDate->format('D'),
                'label' => $currentDate->format('M j'),
                'watch_time' => (int) $watchTime,
            ];
            
            $currentDate->addDay();
        }

        return $weeklyData;
    }

    /**
     * Get monthly watch time data (daily aggregates, derived from events).
     */
    private function getMonthlyWatchTimeData(User $user, $periodStart, $periodEnd): array
    {
        $monthlyData = [];
        $currentDate = $periodStart->copy();
        
        while ($currentDate->lte($periodEnd)) {
            $dayEnd = $currentDate->copy()->endOfDay();

            // Calculate watch time from events for this day
            $dayEvents = VideoWatchEvent::where('user_id', $user->id)
                ->whereBetween('created_at', [$currentDate, $dayEnd])
                ->whereIn('event_type', $this->getPositionEventTypes())
                ->whereNotNull('video_id')
                ->get();
            
            $watchTime = $this->calculateWatchTimeFromEvents($dayEvents);

            $monthlyData[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day' => $currentDate->format('D'),
                'label' => $currentDate->format('M j'),
                'watch_time' => (int) $watchTime,
            ];
            
            $currentDate->addDay();
        }

        return $monthlyData;
    }

    /**
     * Get yearly watch time data (monthly aggregates, derived from events).
     */
    private function getYearlyWatchTimeData(User $user, $periodStart, $periodEnd): array
    {
        $yearlyData = [];
        $currentDate = $periodStart->copy();
        
        while ($currentDate->lte($periodEnd)) {
            $monthEnd = $currentDate->copy()->endOfMonth();
            if ($monthEnd->gt($periodEnd)) {
                $monthEnd = $periodEnd->copy();
            }

            // Calculate watch time from events for this month
            $monthEvents = VideoWatchEvent::where('user_id', $user->id)
                ->whereBetween('created_at', [$currentDate, $monthEnd])
                ->whereIn('event_type', $this->getPositionEventTypes())
                ->whereNotNull('video_id')
                ->get();
            
            $watchTime = $this->calculateWatchTimeFromEvents($monthEvents);

            $yearlyData[] = [
                'date' => $currentDate->format('Y-m'),
                'day' => $currentDate->format('M'),
                'label' => $currentDate->format('M Y'),
                'watch_time' => (int) $watchTime,
            ];
            
            $currentDate->addMonth()->startOfMonth();
        }

        return $yearlyData;
    }

    /**
     * Get peak viewing hours (hourly distribution).
     * 
     * @param User $user
     * @param \Carbon\Carbon $startDate Period start date
     * @param \Carbon\Carbon $endDate Period end date
     * @return array
     */
    private function getPeakViewingHours(User $user, $startDate, $endDate): array
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
     * Get day of week patterns (derived from events).
     * 
     * @param User $user
     * @param \Carbon\Carbon $startDate Period start date
     * @param \Carbon\Carbon $endDate Period end date
     * @return array
     */
    private function getDayOfWeekPatterns(User $user, $startDate, $endDate): array
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

        // Calculate from events - group by day, then calculate watch time per day
        $events = VideoWatchEvent::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('event_type', $this->getPositionEventTypes())
            ->whereNotNull('video_id')
            ->get();

        // Group events by day of week and calculate watch time per day
        $eventsByDay = $events->groupBy(function ($event) {
            return $event->created_at->format('l');
        });

        foreach ($eventsByDay as $dayName => $dayEvents) {
            if (isset($dayPatterns[$dayName])) {
                // Calculate watch time for this day using helper method
                $dayPatterns[$dayName] += $this->calculateWatchTimeFromEvents($dayEvents);
            }
        }

        return $dayPatterns;
    }

    /**
     * Get daily watch time trend (derived from events).
     */
    private function getDailyWatchTimeTrend(User $user, int $days): array
    {
        $startDate = now()->subDays($days);

        $dailyData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= now()) {
            $dayEnd = $currentDate->copy()->endOfDay();

            // Calculate watch time from events for this day
            $dayEvents = VideoWatchEvent::where('user_id', $user->id)
                ->whereBetween('created_at', [$currentDate, $dayEnd])
                ->whereIn('event_type', $this->getPositionEventTypes())
                ->whereNotNull('video_id')
                ->get();
            
            $watchTime = $this->calculateWatchTimeFromEvents($dayEvents);

            $dailyData[] = [
                'date' => $currentDate->format('Y-m-d'),
                'watch_time' => (int) $watchTime,
            ];

            $currentDate->addDay();
        }

        return $dailyData;
    }

    /**
     * Match started events to target events (completed or abandoned) within a time window.
     * 
     * @param Collection $startedEvents Collection of started events
     * @param Collection $targetEvents Collection of target events (completed or abandoned)
     * @param int $windowHours Time window in hours
     * @param array $usedEventIds Optional array of already-used event IDs to exclude
     * @return array ['matched' => Collection, 'matchedStartedIds' => array, 'usedEventIds' => array]
     */
    private function matchEvents(Collection $startedEvents, Collection $targetEvents, int $windowHours, array $usedEventIds = []): array
    {
        $matched = collect();
        $matchedStartedIds = [];
        $usedIds = $usedEventIds;

        foreach ($startedEvents as $started) {
            // Find the first target event for this video that hasn't been matched yet
            $target = $targetEvents
                ->where('video_id', $started->video_id)
                ->where('created_at', '>=', $started->created_at)
                ->where('created_at', '<=', $started->created_at->copy()->addHours($windowHours))
                ->whereNotIn('id', $usedIds)
                ->first();

            if ($target) {
                $matched->push($target);
                $usedIds[] = $target->id;
                $matchedStartedIds[] = $started->id;
            }
        }

        return [
            'matched' => $matched,
            'matchedStartedIds' => $matchedStartedIds,
            'usedEventIds' => $usedIds,
        ];
    }

    /**
     * Calculate period start and end dates based on period type and offset.
     * 
     * @param string $period Period type: 'week', 'month', or 'year'
     * @param int $offset Offset from current period (0 = current, -1 = previous, etc.)
     * @return array [startDate, endDate]
     */
    private function calculatePeriodDates(string $period, int $offset): array
    {
        $now = now();
        
        return match ($period) {
            'week' => [
                $now->copy()->addWeeks($offset)->startOfWeek(),
                $now->copy()->addWeeks($offset)->endOfWeek(),
            ],
            'month' => [
                $now->copy()->addMonths($offset)->startOfMonth(),
                $now->copy()->addMonths($offset)->endOfMonth(),
            ],
            'year' => [
                $now->copy()->addYears($offset)->startOfYear(),
                $now->copy()->addYears($offset)->endOfYear(),
            ],
            default => [
                $now->copy()->addWeeks($offset)->startOfWeek(),
                $now->copy()->addWeeks($offset)->endOfWeek(),
            ],
        };
    }

    /**
     * Format period label for display.
     * 
     * @param string $period Period type: 'week', 'month', or 'year'
     * @param \Carbon\Carbon $periodStart Period start date
     * @param \Carbon\Carbon $periodEnd Period end date
     * @return string
     */
    private function formatPeriodLabel(string $period, $periodStart, $periodEnd): string
    {
        return match ($period) {
            'week' => $periodStart->format('M j') . ' - ' . $periodEnd->format('M j, Y'),
            'month' => $periodStart->format('F Y'),
            'year' => $periodStart->format('Y'),
            default => $periodStart->format('M j') . ' - ' . $periodEnd->format('M j, Y'),
        };
    }
}

