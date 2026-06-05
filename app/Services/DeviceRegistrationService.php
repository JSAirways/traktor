<?php

namespace App\Services;

use App\Constants\DeviceConstants;
use App\Models\DeviceRegistration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class DeviceRegistrationService
{
    public function __construct(protected DeviceTokenService $tokenService)
    {
    }

    public function registerDevice(User $parent, array $data = []): array
    {
        $deviceFingerprint = $data['device_fingerprint'] ?? null;
        
        // Normalize fingerprint (trim whitespace, validate format)
        if ($deviceFingerprint) {
            $deviceFingerprint = trim($deviceFingerprint);
            // Validate fingerprint format (should be 64 character hex string)
            if (strlen($deviceFingerprint) !== 64 || !ctype_xdigit($deviceFingerprint)) {
                \Log::warning('Invalid device fingerprint format in registerDevice', [
                    'parent_id' => $parent->id,
                    'fingerprint_length' => strlen($deviceFingerprint),
                    'fingerprint_preview' => substr($deviceFingerprint, 0, 10) . '...',
                ]);
                // Set to null to prevent using invalid fingerprint
                $deviceFingerprint = null;
            }
        }
        
        $capabilities = $this->tokenService->normalizeCapabilities($data['capabilities'] ?? []);

        // Always check for existing device by fingerprint first (most reliable)
        if ($deviceFingerprint) {
            $existingDevice = DeviceRegistration::where('parent_user_id', $parent->id)
                ->where('device_fingerprint', $deviceFingerprint)
                ->orderBy('last_used_at', 'desc')
                ->orderBy('registered_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($existingDevice) {
                // Device already exists - reactivate and update
                \Log::info('Device registration: Reactivating existing device', [
                    'parent_id' => $parent->id,
                    'device_id' => $existingDevice->id,
                    'fingerprint' => $deviceFingerprint,
                ]);
                
                $updateData = [
                    'user_agent' => $data['user_agent'] ?? $existingDevice->user_agent,
                    'screen_resolution' => $data['screen_resolution'] ?? $existingDevice->screen_resolution,
                    'is_active' => true,
                    'last_used_at' => now(),
                ];

                if (isset($data['device_name']) && !empty(trim($data['device_name'])) && trim($data['device_name']) !== DeviceConstants::PASSWORD_ONLY_LOGIN_FLAG) {
                    $updateData['device_name'] = trim($data['device_name']);
                }

                $existingDevice->update($updateData);

                $freshDevice = $existingDevice->fresh();
                $token = $this->tokenService->issue($freshDevice, $capabilities);

                return [$freshDevice, $token];
            }
        }

        // If no fingerprint or no match found, create new device
        // Database constraint will catch any race conditions or edge cases
        try {
            \Log::info('Device registration: Creating new device', [
                'parent_id' => $parent->id,
                'has_fingerprint' => !empty($deviceFingerprint),
                'fingerprint' => $deviceFingerprint ? substr($deviceFingerprint, 0, 10) . '...' : null,
            ]);
            
            // Create device with temporary UUID token (will be replaced by DeviceTokenService::issue())
            $device = DeviceRegistration::create([
                'parent_user_id' => $parent->id,
                'device_token' => (string) Str::uuid(), // Temporary token, replaced by issue()
                'device_fingerprint' => $deviceFingerprint,
                'user_agent' => $data['user_agent'] ?? null,
                'screen_resolution' => $data['screen_resolution'] ?? null,
                'device_name' => $data['device_name'] ?? null,
                'registered_at' => now(),
                'is_active' => true,
            ]);

            $device->refresh();
            $token = $this->tokenService->issue($device, $capabilities);

            return [$device, $token];
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle unique constraint violation (duplicate fingerprint)
            // This catches race conditions where two requests try to register simultaneously
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'device_registrations_parent_fingerprint_unique')) {
                \Log::warning('Device registration: Duplicate detected by database constraint (race condition)', [
                    'parent_id' => $parent->id,
                    'fingerprint' => $deviceFingerprint ? substr($deviceFingerprint, 0, 10) . '...' : null,
                ]);
                
                // Duplicate detected by database constraint - find and return existing device
                if ($deviceFingerprint) {
                    $existingDevice = DeviceRegistration::where('parent_user_id', $parent->id)
                        ->where('device_fingerprint', $deviceFingerprint)
                        ->orderBy('last_used_at', 'desc')
                        ->orderBy('registered_at', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($existingDevice) {
                        // Update and reactivate existing device
                        $updateData = [
                            'user_agent' => $data['user_agent'] ?? $existingDevice->user_agent,
                            'screen_resolution' => $data['screen_resolution'] ?? $existingDevice->screen_resolution,
                            'is_active' => true,
                            'last_used_at' => now(),
                        ];

                        if (isset($data['device_name']) && !empty(trim($data['device_name'])) && trim($data['device_name']) !== DeviceConstants::PASSWORD_ONLY_LOGIN_FLAG) {
                            $updateData['device_name'] = trim($data['device_name']);
                        }

                        $existingDevice->update($updateData);

                        $freshDevice = $existingDevice->fresh();
                        $token = $this->tokenService->issue($freshDevice, $capabilities);

                        return [$freshDevice, $token];
                    }
                }

                // Log error if we can't find the existing device
                \Log::error('Device registration: Duplicate constraint violation but existing device not found', [
                    'parent_id' => $parent->id,
                    'fingerprint' => $deviceFingerprint ? substr($deviceFingerprint, 0, 10) . '...' : null,
                    'error' => $e->getMessage(),
                ]);

                // Re-throw if we can't handle it
                throw $e;
            }

            // Re-throw if it's not a duplicate constraint violation
            throw $e;
        }
    }

    public function validateDevice(string $token): ?DeviceRegistration
    {
        if ($this->tokenService->isSignedToken($token)) {
            $payload = $this->tokenService->decode($token);

            if (!$payload) {
                return null;
            }

            $device = DeviceRegistration::with('parent')->find($payload['rid'] ?? null);

            if (!$device || !$device->is_active) {
                return null;
            }

            // Check if token ID matches - if not, token was refreshed and cookie needs update
            $tokenIdMatches = hash_equals($device->device_token ?? '', $payload['tid'] ?? '');
            
            // Solution 1 & 4: Check if token is expired or expiring soon, and refresh if needed
            // Only refresh if token ID matches (token hasn't been refreshed already)
            if ($tokenIdMatches && $device->token_expires_at) {
                if (now()->greaterThan($device->token_expires_at)) {
                    // Token expired - try to refresh it
                    \Log::info('Device token expired, attempting refresh', [
                        'device_id' => $device->id,
                        'parent_id' => $device->parent_user_id,
                        'expired_at' => $device->token_expires_at,
                        'expired_days_ago' => now()->diffInDays($device->token_expires_at),
                    ]);
                    return $this->refreshExpiredToken($device);
                } elseif (now()->addDays(7)->greaterThan($device->token_expires_at)) {
                    // Token expiring within 7 days - proactively refresh
                    \Log::info('Device token expiring soon, proactively refreshing', [
                        'device_id' => $device->id,
                        'parent_id' => $device->parent_user_id,
                        'expires_at' => $device->token_expires_at,
                        'days_until_expiry' => now()->diffInDays($device->token_expires_at, false),
                    ]);
                    return $this->refreshExpiringToken($device);
                }
            }
            
            // If token ID doesn't match, it means token was refreshed but cookie wasn't updated yet
            // Still return the device so it can be used (cookie will be updated in getDeviceFromCookie)
            if (!$tokenIdMatches) {
                \Log::debug('Device token ID mismatch - token was refreshed, cookie needs update', [
                    'device_id' => $device->id,
                    'parent_id' => $device->parent_user_id,
                    'cookie_token_id' => $payload['tid'] ?? null,
                    'device_token_id' => $device->device_token,
                ]);
            }

            // Return device if token ID matches OR if device is active (token was refreshed)
            return $device;
        }

        return DeviceRegistration::where('device_token', $token)
            ->where('is_active', true)
            ->with('parent')
            ->first();
    }

    public function findByToken(string $token): ?DeviceRegistration
    {
        if ($this->tokenService->isSignedToken($token)) {
            $payload = $this->tokenService->decode($token);
            if (!$payload) {
                return null;
            }

            return DeviceRegistration::find($payload['rid'] ?? null);
        }

        return DeviceRegistration::where('device_token', $token)->first();
    }

    public function findByParent(User $parent)
    {
        return DeviceRegistration::where('parent_user_id', $parent->id)
            ->orderBy('last_used_at', 'desc')
            ->orderBy('registered_at', 'desc')
            ->get();
    }

    public function logoutDevice(string $token): bool
    {
        $device = $this->findByToken($token);

        if ($device) {
            $device->deactivate();
            return true;
        }

        return false;
    }

    public function getDeviceFromCookie(?Request $request = null): ?DeviceRegistration
    {
        $token = $request ? $request->cookie('device_token') : Cookie::get('device_token');

        if (!$token) {
            \Log::debug('No device token found in cookie', [
                'has_request' => $request !== null,
            ]);
            return null;
        }

        \Log::debug('Validating device token from cookie', [
            'token_preview' => substr($token, 0, 20) . '...',
            'token_length' => strlen($token),
            'is_signed' => $this->tokenService->isSignedToken($token),
        ]);

        $device = $this->validateDevice($token);
        
        if (!$device) {
            \Log::warning('Device validation failed', [
                'token_preview' => substr($token, 0, 20) . '...',
            ]);
            return null;
        }
        
        // If device was returned but token was refreshed, update cookie
        if ($device && $request) {
            // Check if the current cookie token matches the device's token
            // If not, the token was refreshed and we need to update the cookie
            $currentSignedToken = $this->tokenService->getSignedToken($device);
            if ($currentSignedToken && $currentSignedToken !== $token) {
                // Token was refreshed - update cookie
                \Log::info('Updating device token cookie after refresh', [
                    'device_id' => $device->id,
                    'parent_id' => $device->parent_user_id,
                ]);
                
                $expiration = config('access.device_cookie_expiration', 525600);
                $sessionConfig = config('session');
                $cookiePath = $sessionConfig['path'] ?? '/';
                $cookieDomain = $sessionConfig['domain'] ?? null;
                $cookieSecure = $sessionConfig['secure'] ?? null;
                $cookieSameSite = $sessionConfig['same_site'] ?? 'lax';
                
                Cookie::queue(cookie('device_token', $currentSignedToken, $expiration, $cookiePath, $cookieDomain, $cookieSecure, true, false, $cookieSameSite));
            }
        }
        
        return $device;
    }

    public function isCurrentDevice(DeviceRegistration $device, ?Request $request = null): bool
    {
        $currentDevice = $this->getDeviceFromCookie($request);

        return $currentDevice ? $currentDevice->id === $device->id : false;
    }

    public function clearDeviceCookie(): void
    {
        Cookie::queue(Cookie::forget('device_token'));
        Cookie::queue(Cookie::forget('parent_user_id'));
    }

    public function getParentUserIdFromCookie(?Request $request = null): ?int
    {
        $parentId = $request ? $request->cookie('parent_user_id') : Cookie::get('parent_user_id');
        return $parentId ? (int) $parentId : null;
    }

    public function touchDevice(DeviceRegistration|string|null $deviceOrToken): void
    {
        if ($deviceOrToken instanceof DeviceRegistration) {
            $device = $deviceOrToken;
        } elseif (!empty($deviceOrToken)) {
            $device = $this->findByToken($deviceOrToken);
        } else {
            $device = null;
        }

        if ($device) {
            $device->touchLastUsed();
        }
    }

    public function getUsersByDeviceFingerprint(string $fingerprint)
    {
        try {
            $devices = DeviceRegistration::where('device_fingerprint', $fingerprint)
                ->whereNotNull('device_fingerprint')
                ->whereNotNull('parent_user_id')
                ->with('parent')
                ->orderBy('is_active', 'desc')
                ->orderBy('last_used_at', 'desc')
                ->orderBy('registered_at', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            // Group by parent_user_id and take the most recent device for each user
            // With the unique constraint, there should only be one device per user per fingerprint,
            // but this handles any edge cases gracefully
            return $devices->groupBy('parent_user_id')
                ->map(function ($userDevices) {
                    // Get the most recent device (first in our sorted collection)
                    $device = $userDevices->first();
                    // Ensure parent relationship is loaded and valid
                    if ($device && $device->relationLoaded('parent') && $device->parent !== null) {
                        return $device;
                    }
                    return null;
                })
                ->filter(fn ($device) => $device !== null && $device->parent !== null)
                ->values();
        } catch (\Throwable $e) {
            \Log::error('Error in getUsersByDeviceFingerprint', [
                'fingerprint' => $fingerprint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Return empty collection on error
            return collect();
        }
    }

    public function hasDeviceFingerprintRegistrations(string $fingerprint): bool
    {
        return DeviceRegistration::where('device_fingerprint', $fingerprint)->exists();
    }

    public function findDeviceByFingerprintForOtherUser(string $fingerprint, int $excludeUserId): ?DeviceRegistration
    {
        return DeviceRegistration::where('device_fingerprint', $fingerprint)
            ->whereNotNull('device_fingerprint')
            ->where('parent_user_id', '!=', $excludeUserId)
            ->orderBy('is_active', 'desc')
            ->orderBy('last_used_at', 'desc')
            ->orderBy('registered_at', 'desc')
            ->first();
    }

    public function refreshCapabilities(DeviceRegistration $device, array $capabilities = []): DeviceRegistration
    {
        return $this->tokenService->persistCapabilities($device, $capabilities);
    }

    /**
     * Get the token service instance (for capability hash building).
     */
    public function getTokenService(): DeviceTokenService
    {
        return $this->tokenService;
    }

    /**
     * Refresh an expired token for a device.
     * Uses existing capabilities if available, otherwise empty array.
     */
    protected function refreshExpiredToken(DeviceRegistration $device): ?DeviceRegistration
    {
        // Only refresh if device is still active
        if (!$device->is_active) {
            return null;
        }

        // Use existing capabilities if available
        $capabilities = $device->capabilities ?? [];
        
        // Issue new token with existing capabilities
        $this->tokenService->issue($device, $capabilities);
        
        // Refresh device to get updated token_expires_at
        $device->refresh();
        
        \Log::info('Device token refreshed after expiration', [
            'device_id' => $device->id,
            'parent_id' => $device->parent_user_id,
            'new_expires_at' => $device->token_expires_at,
        ]);
        
        return $device;
    }

    /**
     * Refresh a token that is expiring soon (within 7 days).
     * Uses existing capabilities if available, otherwise empty array.
     */
    protected function refreshExpiringToken(DeviceRegistration $device): ?DeviceRegistration
    {
        // Only refresh if device is still active
        if (!$device->is_active) {
            return $device;
        }

        // Use existing capabilities if available
        $capabilities = $device->capabilities ?? [];
        
        // Issue new token with existing capabilities
        $this->tokenService->issue($device, $capabilities);
        
        // Refresh device to get updated token_expires_at
        $device->refresh();
        
        \Log::info('Device token proactively refreshed before expiration', [
            'device_id' => $device->id,
            'parent_id' => $device->parent_user_id,
            'new_expires_at' => $device->token_expires_at,
        ]);
        
        return $device;
    }

    /**
     * Refresh device token (for use when capability hash changes or manual refresh).
     * 
     * @param DeviceRegistration $device
     * @param array $capabilities Optional new capabilities. If not provided, uses existing capabilities.
     * @return DeviceRegistration
     */
    public function refreshDeviceToken(DeviceRegistration $device, array $capabilities = []): DeviceRegistration
    {
        // Use provided capabilities or existing ones
        $capabilitiesToUse = !empty($capabilities) ? $capabilities : ($device->capabilities ?? []);
        
        // Issue new token
        $this->tokenService->issue($device, $capabilitiesToUse);
        
        // Refresh device to get updated token_expires_at
        $device->refresh();
        
        \Log::info('Device token manually refreshed', [
            'device_id' => $device->id,
            'parent_id' => $device->parent_user_id,
            'new_expires_at' => $device->token_expires_at,
            'has_new_capabilities' => !empty($capabilities),
        ]);
        
        return $device;
    }

    /**
     * Check if a device token is expired.
     */
    public function isTokenExpired(DeviceRegistration $device): bool
    {
        if (!$device->token_expires_at) {
            return false; // Legacy tokens without expiration are considered valid
        }
        
        return now()->greaterThan($device->token_expires_at);
    }
}

