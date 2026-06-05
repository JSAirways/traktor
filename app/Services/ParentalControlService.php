<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\ParentalControls;
use App\Models\User;
use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ParentalControlService
{
    /**
     * Check if user can watch a video based on parental controls.
     */
    public function canWatchVideo(User $user, Video $video): bool
    {
        $controls = $user->getParentalControls();
        
        // If no controls set, allow access
        if ($controls->isEmpty()) {
            return true;
        }
        
        // Check time restrictions
        if ($controls->timeRestrictions && !$this->isWithinTimeRestrictions($controls->timeRestrictions)) {
            return false;
        }
        
        // Check channel restrictions
        if ($controls->blockedChannels && !empty($controls->blockedChannels)) {
            if (in_array($video->channel_id, $controls->blockedChannels)) {
                return false;
            }
        }
        
        if ($controls->allowedChannels && !empty($controls->allowedChannels)) {
            if (!in_array($video->channel_id, $controls->allowedChannels)) {
                return false;
            }
        }
        
        // Check daily limit
        if ($controls->dailyLimitMinutes && $this->hasExceededDailyLimit($user, $controls->dailyLimitMinutes)) {
            return false;
        }
        
        // Check video length limit
        if ($controls->maxVideoLengthMinutes && $video->duration > ($controls->maxVideoLengthMinutes * 60)) {
            return false;
        }
        
        // Check blocked keywords in title
        if ($controls->blockedKeywords && !empty($controls->blockedKeywords)) {
            $titleLower = mb_strtolower($video->title);
            foreach ($controls->blockedKeywords as $keyword) {
                if (str_contains($titleLower, mb_strtolower($keyword))) {
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Check if current time is within allowed time restrictions.
     */
    protected function isWithinTimeRestrictions(array $timeRestrictions): bool
    {
        if (!isset($timeRestrictions['start']) || !isset($timeRestrictions['end'])) {
            return true; // Invalid restrictions, allow access
        }
        
        $now = Carbon::now();
        $startTime = Carbon::parse($timeRestrictions['start']);
        $endTime = Carbon::parse($timeRestrictions['end']);
        
        // Handle overnight restrictions (e.g., 20:00 to 08:00)
        if ($startTime->greaterThan($endTime)) {
            return $now->greaterThanOrEqualTo($startTime) || $now->lessThanOrEqualTo($endTime);
        }
        
        return $now->greaterThanOrEqualTo($startTime) && $now->lessThanOrEqualTo($endTime);
    }

    /**
     * Check if user has exceeded daily watch limit.
     */
    protected function hasExceededDailyLimit(User $user, int $dailyLimitMinutes): bool
    {
        // TODO: Implement watch history tracking
        // For now, this is a placeholder that always returns false
        // Once watch history is implemented, query the total watch time for today
        
        // Example implementation (when watch history exists):
        // $todayWatchTime = DB::table('watch_history')
        //     ->where('user_id', $user->id)
        //     ->whereDate('watched_at', Carbon::today())
        //     ->sum('watch_duration');
        // 
        // return ($todayWatchTime / 60) >= $dailyLimitMinutes;
        
        return false;
    }

    /**
     * Get remaining daily watch time in minutes.
     */
    public function getRemainingDailyWatchTime(User $user): ?int
    {
        $controls = $user->getParentalControls();
        
        if (!$controls->dailyLimitMinutes) {
            return null; // No limit set
        }
        
        // TODO: Implement when watch history tracking is added
        $usedMinutes = 0; // Placeholder
        
        return max(0, $controls->dailyLimitMinutes - $usedMinutes);
    }
}

