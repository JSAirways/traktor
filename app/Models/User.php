<?php

namespace App\Models;

use App\Notifications\ResetPassword as ResetPasswordNotification;
use App\Notifications\VerifyEmail as VerifyEmailNotification;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements HasLocalePreference
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'slug',
        'email',
        'password',
        'username',
        'role',
        'profile_picture',
        'profile_picture_category',
        'parent_id',
        'view_pin',
        'admin_pin',
        'is_viewable',
        'appears_in_profile_selection',
        'account_status',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'locale',
        'how_heard_about',
        'parental_controls',
        'channel_order',
        'show_all_content_section',
        'hidden_channels',
        'cache_version',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'view_pin',
        'admin_pin',
    ];

    protected $casts = [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        'approved_at' => 'datetime',
        'parental_controls' => 'array',
        'is_viewable' => 'boolean',
        'appears_in_profile_selection' => 'boolean',
        'channel_order' => 'array',
        'show_all_content_section' => 'boolean',
        'hidden_channels' => 'array',
        'cache_version' => 'datetime',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }

    public function playlists()
    {
        return $this->hasMany(Playlist::class);
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function deviceRegistrations()
    {
        return $this->hasMany(DeviceRegistration::class, 'parent_user_id');
    }

    public function watchEvents()
    {
        return $this->hasMany(VideoWatchEvent::class);
    }

    public function watchSessions()
    {
        return $this->hasMany(WatchSession::class);
    }

    public function scopePending($query)
    {
        return $query->where('account_status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('account_status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('account_status', 'rejected');
    }

    public function scopeViewable($query)
    {
        return $query->where('is_viewable', true);
    }

    public function isPending(): bool
    {
        return $this->account_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->account_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->account_status === 'rejected';
    }

    public function approve(User $admin): void
    {
        $this->update([
            'account_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);
    }

    public function reject(string $reason, User $admin): void
    {
        $this->update([
            'account_status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => $admin->id,
        ]);
    }

    public function generateViewPin(int $length = 4): string
    {
        $pin = str_pad((string) random_int(10 ** ($length - 1), (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $this->setViewPin($pin);

        return $pin;
    }

    public function setViewPin(string $pin): void
    {
        $this->view_pin = \Crypt::encryptString($pin);
        $this->save();
    }

    public function getViewPin(): ?string
    {
        if (empty($this->view_pin)) {
            return null;
        }

        try {
            return \Crypt::decryptString($this->view_pin);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function verifyPin(string $pin): bool
    {
        if (empty($this->view_pin)) {
            return true;
        }

        try {
            $storedPin = \Crypt::decryptString($this->view_pin);
            return $storedPin === $pin;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function hasPin(): bool
    {
        return !empty($this->view_pin);
    }

    public function hasStoredPin(): bool
    {
        return !empty($this->view_pin);
    }

    public function generateAdminPin(int $length = 4): string
    {
        $pin = str_pad((string) random_int(10 ** ($length - 1), (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $this->setAdminPin($pin);

        return $pin;
    }

    public function setAdminPin(string $pin): void
    {
        $this->admin_pin = \Crypt::encryptString($pin);
        $this->save();
    }

    public function getAdminPin(): ?string
    {
        if (empty($this->admin_pin)) {
            return null;
        }

        try {
            return \Crypt::decryptString($this->admin_pin);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function verifyAdminPin(string $pin): bool
    {
        if (empty($this->admin_pin)) {
            return false;
        }

        try {
            $storedPin = \Crypt::decryptString($this->admin_pin);
            return $storedPin === $pin;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function hasAdminPin(): bool
    {
        return !empty($this->admin_pin);
    }

    /**
     * Public URL for the user's profile picture, if set.
     */
    public function profilePictureUrl(): ?string
    {
        if (!$this->profile_picture) {
            return null;
        }

        $category = $this->profile_picture_category ?? 'cats';

        return asset('assets/profile-pictures/' . $category . '/' . $this->profile_picture);
    }

    public function canManage(User $user): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($user->parent_id === $this->id) {
            return true;
        }

        return $this->id === $user->id;
    }

    /**
     * Get parental controls as value object.
     */
    public function getParentalControls(): \App\DataTransferObjects\ParentalControls
    {
        return \App\DataTransferObjects\ParentalControls::fromArray($this->parental_controls ?? []);
    }

    /**
     * Set parental controls from value object.
     */
    public function setParentalControls(\App\DataTransferObjects\ParentalControls $controls): void
    {
        $this->parental_controls = $controls->toArray();
        $this->save();
    }

    /**
     * Get a specific parental control value (backward compatibility).
     * 
     * @deprecated Use getParentalControls() instead
     */
    public function getParentalControl(string $key, $default = null)
    {
        $controls = $this->getParentalControls();
        return match($key) {
            'max_watch_time_minutes', 'maxWatchTimeMinutes' => $controls->maxWatchTimeMinutes ?? $default,
            'allowed_channels', 'allowedChannels' => $controls->allowedChannels ?? $default,
            'blocked_channels', 'blockedChannels' => $controls->blockedChannels ?? $default,
            'content_rating', 'contentRating' => $controls->contentRating ?? $default,
            'allow_comments', 'allowComments' => $controls->allowComments ?? $default,
            'allow_live_streams', 'allowLiveStreams' => $controls->allowLiveStreams ?? $default,
            'time_restrictions', 'timeRestrictions' => $controls->timeRestrictions ?? $default,
            'daily_limit_minutes', 'dailyLimitMinutes' => $controls->dailyLimitMinutes ?? $default,
            'blocked_keywords', 'blockedKeywords' => $controls->blockedKeywords ?? $default,
            'max_video_length_minutes', 'maxVideoLengthMinutes' => $controls->maxVideoLengthMinutes ?? $default,
            default => $default,
        };
    }

    /**
     * Set a specific parental control value (backward compatibility).
     * 
     * @deprecated Use setParentalControls() instead
     */
    public function setParentalControl(string $key, $value): void
    {
        $controls = $this->getParentalControls();
        
        match($key) {
            'max_watch_time_minutes', 'maxWatchTimeMinutes' => $controls->maxWatchTimeMinutes = $value,
            'allowed_channels', 'allowedChannels' => $controls->allowedChannels = $value,
            'blocked_channels', 'blockedChannels' => $controls->blockedChannels = $value,
            'content_rating', 'contentRating' => $controls->contentRating = $value,
            'allow_comments', 'allowComments' => $controls->allowComments = $value,
            'allow_live_streams', 'allowLiveStreams' => $controls->allowLiveStreams = $value,
            'time_restrictions', 'timeRestrictions' => $controls->timeRestrictions = $value,
            'daily_limit_minutes', 'dailyLimitMinutes' => $controls->dailyLimitMinutes = $value,
            'blocked_keywords', 'blockedKeywords' => $controls->blockedKeywords = $value,
            'max_video_length_minutes', 'maxVideoLengthMinutes' => $controls->maxVideoLengthMinutes = $value,
            default => null,
        };
        
        $this->setParentalControls($controls);
    }

    public function getCacheVersionTimestamp(): int
    {
        if (!$this->cache_version) {
            return 0;
        }

        if ($this->cache_version instanceof \Carbon\Carbon) {
            return $this->cache_version->timestamp;
        }

        if (is_string($this->cache_version)) {
            return strtotime($this->cache_version) ?: 0;
        }

        return 0;
    }

    public static function generateSlugFromUsername(string $username): string
    {
        $slug = mb_strtolower($username, 'UTF-8');
        $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $slug);
        $slug = preg_replace('/[\s]+/u', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        if (empty($slug)) {
            $slug = 'user-' . uniqid();
        }

        return preg_replace('/[^a-z0-9_-]/', '', $slug);
    }

    public static function generateUniqueSlugFromUsername(string $username, ?int $excludeUserId = null): string
    {
        $baseSlug = self::generateSlugFromUsername($username);
        $slug = $baseSlug;
        $counter = 1;

        while (self::where('slug', $slug)
            ->when($excludeUserId, fn ($query) => $query->where('id', '!=', $excludeUserId))
            ->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($user) {
            // Normalize email to lowercase (emails are case-insensitive)
            if ($user->isDirty('email') && $user->email !== null) {
                $user->email = strtolower($user->email);
            }

            if ($user->isDirty('username') || empty($user->slug)) {
                $user->slug = self::generateUniqueSlugFromUsername($user->username ?? '', $user->id);
            }
        });
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification());
    }

    public function preferredLocale()
    {
        return $this->locale;
    }
}
