<?php

use Illuminate\Support\Facades\Route;

// Home page (profile selection) - redirects to /welcome if device not registered
Route::get('/', [App\Http\Controllers\WelcomeController::class, 'home'])->name('home');

// Welcome page (user selection) - shown when device not registered
Route::get('/welcome', [App\Http\Controllers\WelcomeController::class, 'welcome'])->name('welcome');

// Device registration routes
Route::get('/register-device', [App\Http\Controllers\DeviceController::class, 'showRegistrationForm'])->name('device.register.show');
Route::post('/register-device', [App\Http\Controllers\DeviceController::class, 'register'])->name('device.register');

// Account registration routes (public registration - guests only)
Route::middleware('guest')->group(function () {
    Route::get('/register-account', [App\Http\Controllers\Auth\RegisteredUserController::class, 'registerAccount'])->name('register-account');
    Route::post('/register-account', [App\Http\Controllers\Auth\RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,15')
        ->name('register-account.store');
});
Route::post('/device/logout', [App\Http\Controllers\DeviceController::class, 'logout'])->name('device.logout');
Route::post('/api/device/registered-users', [App\Http\Controllers\DeviceController::class, 'getRegisteredUsers'])->name('api.device.registered-users');
Route::post('/api/device/generate-fingerprint', [App\Http\Controllers\DeviceController::class, 'generateFingerprint'])->name('api.device.generate-fingerprint');
Route::post('/api/device/refresh-capabilities', [App\Http\Controllers\DeviceController::class, 'refreshCapabilities'])->name('api.device.refresh-capabilities');

// API endpoints (also in api.php, but adding here as fallback for session support)
Route::middleware([
    \App\Http\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
])->group(function () {
    // User videos endpoint
    Route::get('/api/user/{slug}/videos', [App\Http\Controllers\Api\VideoApiController::class, 'getUserVideos'])
        ->where('slug', '[a-z0-9_-]+');
    
    // Playlist videos endpoint
    Route::get('/api/playlist/{playlistId}/videos', [App\Http\Controllers\Api\VideoApiController::class, 'getPlaylistVideos'])
        ->where('playlistId', '[0-9]+');
});

// Analytics tracking endpoints
// Defined directly like other POST API routes (api/device/*, api/view/validate-pin)
// These routes are excluded from CSRF in VerifyCsrfToken middleware
Route::post('/api/analytics/track', [App\Http\Controllers\Api\AnalyticsController::class, 'track']);
Route::post('/api/analytics/session/start', [App\Http\Controllers\Api\AnalyticsController::class, 'startSession']);
Route::post('/api/analytics/session/end', [App\Http\Controllers\Api\AnalyticsController::class, 'endSession']);

// Access control (viewing sessions)
// PIN entry is handled via modal on gallery page, not a separate route
Route::post('/view/validate', [App\Http\Controllers\ViewingSessionController::class, 'validatePin'])
    ->middleware('rate.limit.pin')
    ->name('view.validate');
Route::post('/api/view/validate-pin', [App\Http\Controllers\ViewingSessionController::class, 'validatePinAjax'])
    ->middleware('rate.limit.pin')
    ->name('api.view.validate-pin');
Route::get('/view/{slug}', [App\Http\Controllers\ViewingSessionController::class, 'directAccess'])
    ->where('slug', '[a-z0-9_-]+')
    ->name('view.direct-access');

// Profile selection page (legacy route - redirects to home)
Route::get('/profile-selection', function() {
    return redirect()->route('home');
})->name('profile-selection');

// Locale switching route
Route::post('/locale/switch', [App\Http\Controllers\LocaleController::class, 'switch'])->name('locale.switch');

// CSRF token refresh route (no auth required - used for refreshing tokens in cached pages)
Route::get('/csrf-token', function() {
    return response()->json(['token' => csrf_token()]);
})->name('csrf-token')->middleware('web');

// Admin password verification route (no auth middleware - uses device registration)
// CSRF protection excluded - device registration + password + rate limiting provide sufficient security
Route::post('/admin/verify-password', [App\Http\Controllers\WelcomeController::class, 'verifyAdminPassword'])
    ->middleware('throttle:5,15') // 5 attempts per 15 minutes
    ->name('admin.verify-password');

// Email action routes (signed URLs for security, no auth middleware)
Route::get('/admin/users/{user}/approve-from-email', [App\Http\Controllers\Admin\UserController::class, 'approveFromEmail'])
    ->name('admin.users.approve-from-email')
    ->middleware('signed');
Route::get('/admin/users/{user}/reject-from-email', [App\Http\Controllers\Admin\UserController::class, 'rejectFromEmail'])
    ->name('admin.users.reject-from-email')
    ->middleware('signed');

// Redirect /admin to /admin/ (content page) - if not logged in, redirect to welcome
Route::get('/admin', function () {
    if (auth()->check()) {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('welcome');
});

require __DIR__.'/auth.php';

// Gallery viewing route - placed after auth routes to avoid conflicts with /register, etc.
// Requires valid viewing session via middleware
Route::get('/{slug}/gallery', [App\Http\Controllers\GalleryController::class, 'show'])
    ->middleware('viewing.session')
    ->where('slug', '[a-z0-9_-]+')
    ->name('gallery.show');

// Player routes - placed after gallery route to avoid conflicts
// Single video player
Route::get('/{slug}/player/{videoId}', [App\Http\Controllers\PlayerController::class, 'show'])
    ->middleware('viewing.session')
    ->where('slug', '[a-z0-9_-]+')
    ->where('videoId', '[a-zA-Z0-9_-]+')
    ->name('player.show');

// Playlist player
Route::get('/{slug}/player/playlist/{playlistId}', [App\Http\Controllers\PlayerController::class, 'showPlaylist'])
    ->middleware('viewing.session')
    ->where('slug', '[a-z0-9_-]+')
    ->where('playlistId', '[0-9]+')
    ->name('player.show-playlist');
