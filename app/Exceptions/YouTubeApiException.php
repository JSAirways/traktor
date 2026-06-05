<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class YouTubeApiException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(
        string $message = 'YouTube API error',
        int $code = 0,
        ?\Throwable $previous = null,
        public ?string $videoId = null,
        public ?string $playlistId = null,
        public ?int $httpStatus = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

