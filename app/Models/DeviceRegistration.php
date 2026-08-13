<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceRegistration extends Model
{
    protected $fillable = [
        'parent_user_id',
        'device_uid',
        'device_token',
        'user_agent',
        'screen_resolution',
        'device_name',
        'registered_at',
        'last_used_at',
        'is_active',
        'capabilities',
        'capability_hash',
        'token_expires_at',
        'current_viewing_slug',
        'viewing_validated_at',
        'viewing_expires_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'last_used_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'viewing_validated_at' => 'datetime',
        'viewing_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'capabilities' => 'array',
    ];

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function childVisibility()
    {
        return $this->hasMany(DeviceChildVisibility::class);
    }

    public function visibleChildren()
    {
        return $this->hasMany(DeviceChildVisibility::class)
            ->where('is_visible', true)
            ->with('child');
    }

    public function watchEvents()
    {
        return $this->hasMany(VideoWatchEvent::class);
    }

    public function watchSessions()
    {
        return $this->hasMany(WatchSession::class);
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function touchLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function parseUserAgent(): ?array
    {
        if (empty($this->user_agent)) {
            return null;
        }

        $ua = $this->user_agent;
        $browser = \App\Constants\DeviceConstants::DEFAULT_BROWSER_NAME;
        $os = \App\Constants\DeviceConstants::DEFAULT_OS_NAME;

        if (preg_match('/Edg\/([0-9.]+)/i', $ua, $matches)) {
            $browser = 'Microsoft Edge ' . $matches[1];
        } elseif (preg_match('/Chrome\/([0-9.]+)/i', $ua, $matches) && !preg_match('/Edg/i', $ua)) {
            $browser = 'Chrome ' . $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/i', $ua, $matches)) {
            $browser = 'Firefox ' . $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/i', $ua, $matches) && !preg_match('/Chrome/i', $ua)) {
            $browser = 'Safari ' . $matches[1];
        } elseif (preg_match('/Opera\/([0-9.]+)/i', $ua, $matches) || preg_match('/OPR\/([0-9.]+)/i', $ua, $matches)) {
            $browser = 'Opera ' . ($matches[1] ?? '');
        } elseif (preg_match('/MSIE ([0-9.]+)/i', $ua, $matches) || preg_match('/Trident\/.*rv:([0-9.]+)/i', $ua, $matches)) {
            $browser = 'Internet Explorer ' . ($matches[1] ?? '');
        }

        if (preg_match('/Windows NT 10\.0/i', $ua)) {
            $os = 'Windows 10/11';
        } elseif (preg_match('/Windows NT 6\.3/i', $ua)) {
            $os = 'Windows 8.1';
        } elseif (preg_match('/Windows NT 6\.2/i', $ua)) {
            $os = 'Windows 8';
        } elseif (preg_match('/Windows NT 6\.1/i', $ua)) {
            $os = 'Windows 7';
        } elseif (preg_match('/Windows NT 6\.0/i', $ua)) {
            $os = 'Windows Vista';
        } elseif (preg_match('/Windows NT 5\.1/i', $ua)) {
            $os = 'Windows XP';
        } elseif (preg_match('/Mac OS X ([0-9_]+)/i', $ua, $matches)) {
            $os = 'macOS ' . str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/Macintosh/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
        } elseif (preg_match('/Android ([0-9.]+)/i', $ua, $matches)) {
            $os = 'Android ' . $matches[1];
        } elseif (preg_match('/iPhone OS ([0-9_]+)/i', $ua, $matches) || preg_match('/OS ([0-9_]+)/i', $ua, $matches)) {
            $os = 'iOS ' . str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/iPad/i', $ua)) {
            $os = 'iPadOS';
        }

        return [
            'browser' => $browser,
            'os' => $os,
        ];
    }
}

