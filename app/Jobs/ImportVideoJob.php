<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\Concerns\InvalidatesUserCache;
use App\Services\ContentService;
use App\Services\YouTubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use InvalidatesUserCache;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $videoId,
        public int $userId,
        public ?array $channelInfo = null
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(YouTubeService $youtubeService, ContentService $contentService): void
    {
        try {
            // Fetch video metadata
            $videoMetadata = $youtubeService->fetchVideoMetadata($this->videoId);
            
            // Create video with channel info
            $contentService->createVideoWithChannel($videoMetadata, $this->userId, $this->channelInfo);
            
            // Invalidate user cache after successful import
            $this->invalidateUserCache($this->userId);
            
            Log::info('Video imported successfully', [
                'video_id' => $this->videoId,
                'user_id' => $this->userId,
            ]);
        } catch (\Exception $e) {
            Log::error('Video import failed', [
                'video_id' => $this->videoId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);
            
            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Video import job failed permanently', [
            'video_id' => $this->videoId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}

