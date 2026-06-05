<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

class ParentalControls
{
    /**
     * Create a new ParentalControls instance.
     */
    public function __construct(
        public ?int $maxWatchTimeMinutes = null,
        public ?array $allowedChannels = null,
        public ?array $blockedChannels = null,
        public ?string $contentRating = null,  // 'G', 'PG', 'PG-13', etc.
        public ?bool $allowComments = null,
        public ?bool $allowLiveStreams = null,
        public ?array $timeRestrictions = null,  // ['start' => '08:00', 'end' => '20:00']
        public ?int $dailyLimitMinutes = null,
        public ?array $blockedKeywords = null,
        public ?int $maxVideoLengthMinutes = null,
    ) {
    }

    /**
     * Convert to array for storage.
     */
    public function toArray(): array
    {
        return array_filter(get_object_vars($this), fn($v) => $v !== null);
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            maxWatchTimeMinutes: $data['max_watch_time_minutes'] ?? $data['maxWatchTimeMinutes'] ?? null,
            allowedChannels: $data['allowed_channels'] ?? $data['allowedChannels'] ?? null,
            blockedChannels: $data['blocked_channels'] ?? $data['blockedChannels'] ?? null,
            contentRating: $data['content_rating'] ?? $data['contentRating'] ?? null,
            allowComments: $data['allow_comments'] ?? $data['allowComments'] ?? null,
            allowLiveStreams: $data['allow_live_streams'] ?? $data['allowLiveStreams'] ?? null,
            timeRestrictions: $data['time_restrictions'] ?? $data['timeRestrictions'] ?? null,
            dailyLimitMinutes: $data['daily_limit_minutes'] ?? $data['dailyLimitMinutes'] ?? null,
            blockedKeywords: $data['blocked_keywords'] ?? $data['blockedKeywords'] ?? null,
            maxVideoLengthMinutes: $data['max_video_length_minutes'] ?? $data['maxVideoLengthMinutes'] ?? null,
        );
    }

    /**
     * Check if controls are empty (no restrictions set).
     */
    public function isEmpty(): bool
    {
        return empty($this->toArray());
    }
}

