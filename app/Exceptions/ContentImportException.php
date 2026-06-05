<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ContentImportException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(
        string $message = 'Content import failed',
        int $code = 0,
        ?\Throwable $previous = null,
        public ?string $itemId = null,
        public ?string $itemType = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

