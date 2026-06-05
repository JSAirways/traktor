<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Video;
use App\Models\Playlist;
use App\Services\YouTubeService;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    protected ?YouTubeService $youtubeService = null;

    /**
     * Get YouTubeService instance
     */
    protected function getYouTubeService(): ?YouTubeService
    {
        if ($this->youtubeService === null) {
            try {
                $this->youtubeService = app(YouTubeService::class);
            } catch (\Exception $e) {
                Log::error('Failed to get YouTubeService instance: ' . $e->getMessage());
                return null;
            }
        }
        return $this->youtubeService;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $youtubeService = $this->getYouTubeService();
        if (!$youtubeService) {
            Log::warning('YouTubeService not available. Skipping channel info backfill.');
            return;
        }

        // Check if API key is set
        if (empty(\App\Models\Setting::getApiKey())) {
            Log::warning('YouTube API key not set. Skipping channel info backfill.');
            return;
        }

        // Backfill videos
        $this->backfillVideos($youtubeService);

        // Backfill playlists
        $this->backfillPlaylists($youtubeService);
    }

    /**
     * Backfill channel info for videos
     */
    protected function backfillVideos(YouTubeService $youtubeService): void
    {
        $videos = Video::whereNull('channel_id')->get();
        $videoCount = $videos->count();
        
        if ($videoCount === 0) {
            Log::info('No videos to backfill channel info for.');
            return;
        }

        Log::info("Starting channel info backfill for {$videoCount} videos.");

        $processed = 0;
        $failed = 0;

        foreach ($videos as $video) {
            try {
                $channelInfo = $youtubeService->getVideoChannelInfo($video->video_id);
                
                if ($channelInfo['channel_id']) {
                    $video->update([
                        'channel_id' => $channelInfo['channel_id'],
                        'channel_name' => $channelInfo['channel_name'],
                        'channel_thumbnail' => $channelInfo['channel_thumbnail'],
                    ]);
                    
                    $processed++;
                } else {
                    $failed++;
                    Log::warning("No channel info found for video {$video->id} (video_id: {$video->video_id})");
                }
                
                // Log progress every 10 videos
                if (($processed + $failed) % 10 === 0) {
                    Log::info("Video backfill progress: {$processed} processed, {$failed} failed, " . ($videoCount - $processed - $failed) . " remaining");
                }
                
                // Small delay to avoid rate limiting (0.1 seconds)
                usleep(100000);
            } catch (\Exception $e) {
                $failed++;
                Log::warning("Failed to backfill channel info for video {$video->id} (video_id: {$video->video_id}): " . $e->getMessage());
                
                // Longer delay on error to avoid hammering the API
                usleep(500000); // 0.5 seconds
            }
        }

        Log::info("Video backfill completed: {$processed} processed, {$failed} failed out of {$videoCount} total.");
    }

    /**
     * Backfill channel info for playlists
     */
    protected function backfillPlaylists(YouTubeService $youtubeService): void
    {
        $playlists = Playlist::whereNull('channel_id')->get();
        $playlistCount = $playlists->count();
        
        if ($playlistCount === 0) {
            Log::info('No playlists to backfill channel info for.');
            return;
        }

        Log::info("Starting channel info backfill for {$playlistCount} playlists.");

        $processed = 0;
        $failed = 0;

        foreach ($playlists as $playlist) {
            try {
                $channelInfo = $youtubeService->getPlaylistChannelInfo($playlist->playlist_id);
                
                if ($channelInfo['channel_id']) {
                    $playlist->update([
                        'channel_id' => $channelInfo['channel_id'],
                        'channel_name' => $channelInfo['channel_name'],
                        'channel_thumbnail' => $channelInfo['channel_thumbnail'],
                    ]);
                    
                    $processed++;
                } else {
                    $failed++;
                    Log::warning("No channel info found for playlist {$playlist->id} (playlist_id: {$playlist->playlist_id})");
                }
                
                // Log progress every 10 playlists
                if (($processed + $failed) % 10 === 0) {
                    Log::info("Playlist backfill progress: {$processed} processed, {$failed} failed, " . ($playlistCount - $processed - $failed) . " remaining");
                }
                
                // Small delay to avoid rate limiting (0.1 seconds)
                usleep(100000);
            } catch (\Exception $e) {
                $failed++;
                Log::warning("Failed to backfill channel info for playlist {$playlist->id} (playlist_id: {$playlist->playlist_id}): " . $e->getMessage());
                
                // Longer delay on error to avoid hammering the API
                usleep(500000); // 0.5 seconds
            }
        }

        Log::info("Playlist backfill completed: {$processed} processed, {$failed} failed out of {$playlistCount} total.");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally clear channel info on rollback
        // Uncomment if you want to rollback the channel info
        /*
        Video::whereNotNull('channel_id')->update([
            'channel_id' => null,
            'channel_name' => null,
            'channel_thumbnail' => null,
        ]);
        
        Playlist::whereNotNull('channel_id')->update([
            'channel_id' => null,
            'channel_name' => null,
            'channel_thumbnail' => null,
        ]);
        */
    }
};
