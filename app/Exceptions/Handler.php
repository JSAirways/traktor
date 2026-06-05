<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
        
        // Format custom exceptions for API responses
        $this->renderable(function (\App\Exceptions\ContentImportException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->error($e->getMessage(), [
                    'item_id' => $e->itemId,
                    'item_type' => $e->itemType,
                ], 400);
            }
        });
        
        $this->renderable(function (\App\Exceptions\YouTubeApiException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->error($e->getMessage(), [
                    'video_id' => $e->videoId,
                    'playlist_id' => $e->playlistId,
                    'http_status' => $e->httpStatus,
                ], $e->httpStatus ?? 500);
            }
        });
    }
}
