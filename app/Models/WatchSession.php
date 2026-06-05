<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WatchSession extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'device_registration_id',
        'started_at',
        'ended_at',
        'total_watch_time',
        'videos_watched',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'total_watch_time' => 'integer',
        'videos_watched' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (empty($session->id)) {
                $session->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the user that owns the watch session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the device registration associated with the watch session.
     */
    public function deviceRegistration(): BelongsTo
    {
        return $this->belongsTo(DeviceRegistration::class);
    }

    /**
     * Get the watch events for this session.
     */
    public function events(): HasMany
    {
        return $this->hasMany(VideoWatchEvent::class, 'session_id', 'id');
    }

    /**
     * Calculate total watch time from events.
     */
    public function calculateTotalWatchTime(): int
    {
        $events = $this->events()->orderBy('created_at')->get();
        $totalTime = 0;
        $lastPosition = 0;
        $lastEventTime = null;

        foreach ($events as $event) {
            if ($event->event_type === VideoWatchEvent::EVENT_STARTED || 
                $event->event_type === VideoWatchEvent::EVENT_RESUMED) {
                $lastPosition = $event->position;
                $lastEventTime = $event->created_at;
            } elseif ($event->event_type === VideoWatchEvent::EVENT_PAUSED ||
                      $event->event_type === VideoWatchEvent::EVENT_COMPLETED ||
                      $event->event_type === VideoWatchEvent::EVENT_ABANDONED) {
                if ($lastEventTime) {
                    $duration = $lastEventTime->diffInSeconds($event->created_at);
                    $totalTime += min($duration, $event->position - $lastPosition);
                }
                $lastEventTime = null;
            } elseif ($event->event_type === VideoWatchEvent::EVENT_POSITION_UPDATE) {
                if ($lastEventTime) {
                    $duration = $lastEventTime->diffInSeconds($event->created_at);
                    $totalTime += min($duration, $event->position - $lastPosition);
                    $lastPosition = $event->position;
                    $lastEventTime = $event->created_at;
                }
            }
        }

        return $totalTime;
    }

    /**
     * Get number of videos watched in this session.
     */
    public function getVideosWatched(): int
    {
        return $this->events()
            ->where('event_type', VideoWatchEvent::EVENT_STARTED)
            ->whereNotNull('video_id')
            ->distinct()
            ->count('video_id');
    }

    /**
     * End the session and calculate totals.
     */
    public function end(): void
    {
        $this->ended_at = now();
        $this->total_watch_time = $this->calculateTotalWatchTime();
        $this->videos_watched = $this->getVideosWatched();
        $this->save();
    }
}
