<?php

declare(strict_types=1);

namespace App\View\Composers;

use Illuminate\View\View;

class PlayerComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $data = $view->getData();
        
        // If controller already set playlistData, don't override it
        if (isset($data['playlistData']) && is_array($data['playlistData']) && isset($data['playlistData']['videos'])) {
            // Controller has already set playlistData correctly, don't override
            // Log for debugging
            \Log::debug('PlayerComposer: Preserving controller playlistData', [
                'videos_count' => count($data['playlistData']['videos'] ?? []),
            ]);
            return;
        }
        
        // Otherwise, process playlist data if available (for backward compatibility)
        $playlistData = null;
        $currentVideoId = $data['videoId'] ?? ($data['video']->video_id ?? null);
        
        if (isset($data['playlist']) && isset($data['videos'])) {
            $playlist = $data['playlist'];
            $videos = $data['videos'];
            $currentIndex = $data['currentIndex'] ?? 0;
            
            $playlistData = [
                'id' => $playlist->id,
                'playlist_id' => $playlist->playlist_id,
                'title' => $playlist->title,
                'videos' => $videos->map(function($video, $index) {
                    return [
                        'id' => $video->id,
                        'video_id' => $video->video_id,
                        'title' => $video->title ?? null,
                        'duration' => $video->duration ?? null,
                        'index' => $index,
                    ];
                })->toArray(),
            ];
            
            // Get current video ID from playlist videos
            if (isset($videos[$currentIndex])) {
                $currentVideoId = $videos[$currentIndex]->video_id;
            }
            
            // Set playlistData if we created it
            if ($playlistData && isset($playlistData['videos']) && !empty($playlistData['videos'])) {
                $view->with('playlistData', $playlistData);
            }
        }
        
        // Always set currentVideoId if we have it
        if ($currentVideoId) {
            $view->with('currentVideoId', $currentVideoId);
        }
    }
}

