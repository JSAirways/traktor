<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    protected $fillable = [
        'user_id',
        'channel_id',
        'channel_name',
        'channel_thumbnail',
        'video_id',
        'title',
        'duration',
        'thumbnail_url',
        'display_order',
        'is_visible',
        'playlist_id',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'duration' => 'integer',
        'display_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function watchEvents(): HasMany
    {
        return $this->hasMany(VideoWatchEvent::class);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}


