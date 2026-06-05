<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class ViewingSessionService
{
    public function __construct(
        protected DeviceRegistrationService $deviceService,
        protected UserLookupService $userLookupService
    ) {
    }

    /**
     * Get or create viewing session using device registration.
     * This avoids conflicts with Laravel session regeneration (e.g., during admin login).
     */
    public function getOrCreateSession(Request $request, string $slug, bool $allowAutoCreate = true): array
    {
        // Get device from cookie
        $device = $this->deviceService->getDeviceFromCookie($request);

        // If device exists and has viewing session, validate it
        if ($device && $device->isActive() && $device->current_viewing_slug && $device->viewing_validated_at) {
            $sessionTimeout = config('access.viewing_session_timeout', 86400);
            
            // Check if session is expired
            if ($device->viewing_expires_at && now()->greaterThan($device->viewing_expires_at)) {
                // Session expired - clear it
                $this->clearDeviceViewingSession($device);
            } elseif ($device->current_viewing_slug === $slug) {
                // Session is valid and matches the requested slug
                // This is the normal case: user has entered PIN and is navigating within the profile
                $user = User::where('slug', $slug)
                    ->where('is_viewable', true)
                    ->first();

                if ($user) {
                    // Valid session exists for this slug - return user immediately
                    // No need to check anything else - session is valid
                    return [true, $user, null];
                } else {
                    // User not found - clear session and redirect to welcome
                    $this->clearDeviceViewingSession($device);
                    return [false, null, 'welcome'];
                }
            } else {
                // If session exists but slug doesn't match, user is switching profiles
                // Clear the old session and require PIN for the new profile
                $this->clearDeviceViewingSession($device);
            }
        }

        // No valid session exists for this slug
        $user = $this->userLookupService->findViewableUserBySlug($request, $slug);

        if (!$user) {
            return [false, null, 'welcome'];
        }

        // Try to auto-create session if allowed (for users without PIN)
        if ($allowAutoCreate && $this->canAutoCreateSession($request, $user)) {
            $this->createSession($request, $user);
            return [true, $user, null];
        }

        // PIN is required - redirect to PIN entry
        if ($user->hasPin()) {
            return [false, $user, 'pin-entry'];
        }

        // No PIN but can't auto-create - redirect to welcome
        return [false, $user, 'welcome'];
    }

    protected function canAutoCreateSession(Request $request, User $user): bool
    {
        if ($user->hasPin()) {
            return false;
        }

        $device = $this->deviceService->getDeviceFromCookie($request);

        if (!$device || !$device->isActive() || !$device->parent) {
            return false;
        }

        if (!$device->relationLoaded('parent')) {
            $device->load('parent');
        }

        return $user->id === $device->parent->id || $user->parent_id === $device->parent->id;
    }

    /**
     * Create viewing session in device registration.
     * This persists across navigation and is not affected by Laravel session regeneration.
     */
    public function createSession(Request $request, User $user): void
    {
        $device = $this->deviceService->getDeviceFromCookie($request);
        
        if (!$device || !$device->isActive()) {
            // No device - fallback to Laravel session for backward compatibility
            // This should rarely happen if device registration is working correctly
            session()->put('viewing_slug', $user->slug);
            session()->put('viewing_validated_at', time());
            session()->save();
            return;
        }

        // Store viewing session in device registration
        $sessionTimeout = config('access.viewing_session_timeout', 86400);
        $expiresAt = now()->addSeconds($sessionTimeout);
        
        $device->update([
            'current_viewing_slug' => $user->slug,
            'viewing_validated_at' => now(),
            'viewing_expires_at' => $expiresAt,
        ]);
    }

    /**
     * Validate viewing session from device registration.
     */
    public function validateSession(Request $request, string $slug): bool
    {
        $device = $this->deviceService->getDeviceFromCookie($request);
        
        if (!$device || !$device->isActive()) {
            // Fallback to Laravel session for backward compatibility
            $viewingSlug = session('viewing_slug');
            $validatedAt = session('viewing_validated_at');
            
            if (!$viewingSlug || !$validatedAt || $viewingSlug !== $slug) {
                return false;
            }
            
            $sessionTimeout = config('access.viewing_session_timeout', 86400);
            $expiresAt = $validatedAt + $sessionTimeout;
            
            return time() <= $expiresAt;
        }

        if (!$device->current_viewing_slug || !$device->viewing_validated_at || $device->current_viewing_slug !== $slug) {
            return false;
        }

        if ($device->viewing_expires_at && now()->greaterThan($device->viewing_expires_at)) {
            $this->clearDeviceViewingSession($device);
            return false;
        }

        return true;
    }

    /**
     * Clear viewing session from device registration.
     */
    public function clearSession(Request $request = null): void
    {
        // Clear from device registration if device exists
        if ($request) {
            $device = $this->deviceService->getDeviceFromCookie($request);
            if ($device) {
                $this->clearDeviceViewingSession($device);
            }
        }
        
        // Also clear Laravel session for backward compatibility
        session()->forget(['viewing_slug', 'viewing_validated_at']);
    }

    /**
     * Clear viewing session from a specific device.
     */
    protected function clearDeviceViewingSession($device): void
    {
        $device->update([
            'current_viewing_slug' => null,
            'viewing_validated_at' => null,
            'viewing_expires_at' => null,
        ]);
    }
}


