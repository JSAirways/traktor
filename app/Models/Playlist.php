<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Playlist extends Model
{
    protected $fillable = [
        'user_id',
        'channel_id',
        'channel_name',
        'channel_thumbnail',
        'playlist_id',
        'title',
        'total_duration',
        'thumbnail_url',
        'display_order',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'total_duration' => 'integer',
        'display_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
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


