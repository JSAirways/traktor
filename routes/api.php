<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API routes that need session support for viewing session validation
// Apply session middleware only - controller handles validation (lighter approach)
// This allows sessions to work while keeping validation logic in the controller
Route::middleware([
    \App\Http\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
])->group(function () {
    Route::get('/user/{slug}/videos', [App\Http\Controllers\Api\VideoApiController::class, 'getUserVideos'])
        ->where('slug', '[a-z0-9_-]+');
    
    // Playlist videos endpoint - must match exactly what frontend expects
    // Frontend calls: /api/playlist/{playlistId}/videos
    Route::get('/playlist/{playlistId}/videos', [App\Http\Controllers\Api\VideoApiController::class, 'getPlaylistVideos'])
        ->where('playlistId', '[0-9]+')
        ->name('api.playlist.videos');
    
    // Player HTML fragment endpoints for AJAX template swapping
    Route::get('/player/{slug}/{videoId}', [App\Http\Controllers\PlayerController::class, 'getPlayerHtml'])
        ->where('slug', '[a-z0-9_-]+')
        ->where('videoId', '[a-zA-Z0-9_-]+');
    Route::get('/player/playlist/{slug}/{playlistId}', [App\Http\Controllers\PlayerController::class, 'getPlaylistPlayerHtml'])
        ->where('slug', '[a-z0-9_-]+')
        ->where('playlistId', '[0-9]+');
    
    // Analytics tracking endpoints - moved to web.php to match pattern of other POST API routes
    // (api/device/*, api/view/validate-pin) which work with CSRF tokens
});
