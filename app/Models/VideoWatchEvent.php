<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoWatchEvent extends Model
{
    protected $fillable = [
        'user_id',
        'video_id',
        'playlist_id',
        'device_registration_id',
        'event_type',
        'position',
        'duration',
        'completion_percentage',
        'session_id',
    ];

    protected $casts = [
        'position' => 'integer',
        'duration' => 'integer',
        'completion_percentage' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    // Event types
    public const EVENT_STARTED = 'started';
    public const EVENT_PAUSED = 'paused';
    public const EVENT_RESUMED = 'resumed';
    public const EVENT_COMPLETED = 'completed';
    public const EVENT_ABANDONED = 'abandoned';
    public const EVENT_POSITION_UPDATE = 'position_update';

    /**
     * Get the user that owns the watch event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the video associated with the watch event.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * Get the playlist associated with the watch event.
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    /**
     * Get the device registration associated with the watch event.
     */
    public function deviceRegistration(): BelongsTo
    {
        return $this->belongsTo(DeviceRegistration::class, 'device_registration_id');
    }

    /**
     * Scope to filter events for a specific user.
     */
    public function scopeForUser($query, int $userId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter events within the last N days.
     */
    public function scopeWithinDays($query, int $days): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to filter events by event type.
     */
    public function scopeByEventType($query, string $eventType): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to get recent events.
     */
    public function scopeRecent($query, int $limit = 50): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
