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

    /**
     * Normalize a client-supplied device UID or mint a new one.
     */
    public function normalizeOrMintDeviceUid(?string $deviceUid): string
    {
        $deviceUid = $deviceUid !== null ? trim($deviceUid) : '';

        if ($this->isValidDeviceUid($deviceUid)) {
            return strtolower($deviceUid);
        }

        return (string) Str::uuid();
    }

    public function isValidDeviceUid(?string $deviceUid): bool
    {
        if ($deviceUid === null || $deviceUid === '') {
            return false;
        }

        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            trim($deviceUid)
        );
    }

    public function registerDevice(User $parent, array $data = []): array
    {
        $deviceUid = $this->normalizeOrMintDeviceUid($data['device_uid'] ?? null);
        $capabilities = $this->tokenService->normalizeCapabilities($data['capabilities'] ?? []);

        $existingDevice = DeviceRegistration::where('parent_user_id', $parent->id)
            ->where('device_uid', $deviceUid)
            ->orderBy('last_used_at', 'desc')
            ->orderBy('registered_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($existingDevice) {
            \Log::info('Device registration: Reactivating existing device', [
                'parent_id' => $parent->id,
                'device_id' => $existingDevice->id,
                'device_uid' => $deviceUid,
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

        try {
            \Log::info('Device registration: Creating new device', [
                'parent_id' => $parent->id,
                'device_uid' => $deviceUid,
            ]);

            $device = DeviceRegistration::create([
                'parent_user_id' => $parent->id,
                'device_uid' => $deviceUid,
                'device_token' => (string) Str::uuid(),
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
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'device_registrations_parent_device_uid_unique')) {
                \Log::warning('Device registration: Duplicate device_uid detected by constraint', [
                    'parent_id' => $parent->id,
                    'device_uid' => $deviceUid,
                ]);

                $existingDevice = DeviceRegistration::where('parent_user_id', $parent->id)
                    ->where('device_uid', $deviceUid)
                    ->orderBy('last_used_at', 'desc')
                    ->orderBy('registered_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($existingDevice) {
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

                \Log::error('Device registration: Duplicate constraint but existing device not found', [
                    'parent_id' => $parent->id,
                    'device_uid' => $deviceUid,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            throw $e;
        }
    }

    public function validateDevice(string $token): ?DeviceRegistration
    {
        if ($this->tokenService->isSignedToken($token)) {
            // Allow expired payloads so grace-period refresh can run.
            $payload = $this->tokenService->decodeAllowExpired($token);

            if (!$payload) {
                return null;
            }

            $device = DeviceRegistration::with('parent')->find($payload['rid'] ?? null);

            if (!$device || !$device->is_active) {
                return null;
            }

            $tokenIdMatches = hash_equals($device->device_token ?? '', $payload['tid'] ?? '');
            $graceMinutes = (int) config('access.device_token_grace_minutes', 129600);
            $payloadExp = isset($payload['exp']) ? (int) $payload['exp'] : null;
            $nowTs = now()->timestamp;
            $expiresAt = $device->token_expires_at;
            $isExpired = $expiresAt && now()->greaterThan($expiresAt);
            $payloadExpired = $payloadExp !== null && $nowTs > $payloadExp;

            // Reject tokens that are expired beyond grace, even when tid was rotated
            // (stale cookie after refresh) so old payloads cannot soft-login forever.
            if ($isExpired || $payloadExpired) {
                $referenceExpiry = $expiresAt?->getTimestamp() ?? $payloadExp;
                $graceDeadline = $referenceExpiry + ($graceMinutes * 60);

                if ($nowTs > $graceDeadline) {
                    \Log::info('Device token expired beyond grace period', [
                        'device_id' => $device->id,
                        'parent_id' => $device->parent_user_id,
                        'expired_at' => $expiresAt,
                        'grace_minutes' => $graceMinutes,
                        'token_id_matches' => $tokenIdMatches,
                    ]);
                    return null;
                }

                if ($tokenIdMatches) {
                    \Log::info('Device token expired within grace, refreshing', [
                        'device_id' => $device->id,
                        'parent_id' => $device->parent_user_id,
                    ]);

                    return $this->refreshExpiredToken($device);
                }

                // tid mismatch + within grace: accept device so cookie can be updated
                \Log::debug('Device token ID mismatch within grace - cookie needs update', [
                    'device_id' => $device->id,
                    'parent_id' => $device->parent_user_id,
                ]);
                return $device;
            }

            if ($tokenIdMatches && $expiresAt && now()->addDays(7)->greaterThan($expiresAt)) {
                \Log::info('Device token expiring soon, proactively refreshing', [
                    'device_id' => $device->id,
                    'parent_id' => $device->parent_user_id,
                    'expires_at' => $expiresAt,
                ]);
                return $this->refreshExpiringToken($device);
            }

            if (!$tokenIdMatches) {
                \Log::debug('Device token ID mismatch - token was refreshed, cookie needs update', [
                    'device_id' => $device->id,
                    'parent_id' => $device->parent_user_id,
                    'cookie_token_id' => $payload['tid'] ?? null,
                    'device_token_id' => $device->device_token,
                ]);
            }

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
            $payload = $this->tokenService->decodeAllowExpired($token);
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

        if ($device && $request) {
            $currentSignedToken = $this->tokenService->getSignedToken($device);
            if ($currentSignedToken && $currentSignedToken !== $token) {
                \Log::info('Updating device token cookie after refresh', [
                    'device_id' => $device->id,
                    'parent_id' => $device->parent_user_id,
                ]);

                $this->queueDeviceCookies($device, $currentSignedToken);
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
        Cookie::queue(Cookie::forget(DeviceConstants::DEVICE_UID_COOKIE));
    }

    /**
     * Build device cookies for a response (token, parent, device_uid).
     *
     * @return array{0: \Symfony\Component\HttpFoundation\Cookie, 1: \Symfony\Component\HttpFoundation\Cookie, 2: \Symfony\Component\HttpFoundation\Cookie}
     */
    public function makeDeviceCookies(DeviceRegistration $device, string $signedToken): array
    {
        $expiration = config('access.device_cookie_expiration', 259200);
        $sessionConfig = config('session');
        $cookiePath = $sessionConfig['path'] ?? '/';
        $cookieDomain = $sessionConfig['domain'] ?? null;
        $cookieSecure = $sessionConfig['secure'] ?? null;
        $cookieSameSite = $sessionConfig['same_site'] ?? 'lax';

        return [
            cookie('device_token', $signedToken, $expiration, $cookiePath, $cookieDomain, $cookieSecure, true, false, $cookieSameSite),
            cookie('parent_user_id', $device->parent_user_id, $expiration, $cookiePath, $cookieDomain, $cookieSecure, true, false, $cookieSameSite),
            cookie(DeviceConstants::DEVICE_UID_COOKIE, $device->device_uid, $expiration, $cookiePath, $cookieDomain, $cookieSecure, true, false, $cookieSameSite),
        ];
    }

    public function queueDeviceCookies(DeviceRegistration $device, string $signedToken): void
    {
        [$tokenCookie, $parentCookie, $uidCookie] = $this->makeDeviceCookies($device, $signedToken);
        Cookie::queue($tokenCookie);
        Cookie::queue($parentCookie);
        Cookie::queue($uidCookie);
    }

    public function getParentUserIdFromCookie(?Request $request = null): ?int
    {
        $parentId = $request ? $request->cookie('parent_user_id') : Cookie::get('parent_user_id');
        return $parentId ? (int) $parentId : null;
    }

    public function getDeviceUidFromCookie(?Request $request = null): ?string
    {
        $uid = $request
            ? $request->cookie(DeviceConstants::DEVICE_UID_COOKIE)
            : Cookie::get(DeviceConstants::DEVICE_UID_COOKIE);

        return $this->isValidDeviceUid($uid) ? strtolower(trim((string) $uid)) : null;
    }

    /**
     * Resolve the durable device_uid for a request.
     * Prefer the HttpOnly cookie (server-set) over client body/query to avoid
     * minting duplicates when localStorage fails and to block uid fixation.
     */
    public function resolveDeviceUidFromRequest(Request $request, ?string $clientUid = null): string
    {
        $cookieUid = $this->getDeviceUidFromCookie($request);
        if ($cookieUid) {
            return $cookieUid;
        }

        return $this->normalizeOrMintDeviceUid($clientUid ?? $request->input('device_uid'));
    }

    /**
     * Resolve device_uid for known-account discovery (no minting).
     * Cookie wins over body when both are present.
     */
    public function resolveDiscoveryDeviceUid(Request $request, ?string $clientUid = null): ?string
    {
        $cookieUid = $this->getDeviceUidFromCookie($request);
        if ($cookieUid) {
            return $cookieUid;
        }

        $clientUid = $clientUid ?? $request->input('device_uid');
        return $this->isValidDeviceUid($clientUid) ? strtolower(trim((string) $clientUid)) : null;
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

    public function getUsersByDeviceUid(string $deviceUid)
    {
        try {
            if (!$this->isValidDeviceUid($deviceUid)) {
                return collect();
            }

            $deviceUid = strtolower(trim($deviceUid));

            $devices = DeviceRegistration::where('device_uid', $deviceUid)
                ->whereNotNull('parent_user_id')
                ->with('parent')
                ->orderBy('is_active', 'desc')
                ->orderBy('last_used_at', 'desc')
                ->orderBy('registered_at', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            return $devices->groupBy('parent_user_id')
                ->map(function ($userDevices) {
                    $device = $userDevices->first();
                    if ($device && $device->relationLoaded('parent') && $device->parent !== null) {
                        return $device;
                    }
                    return null;
                })
                ->filter(fn ($device) => $device !== null && $device->parent !== null)
                ->values();
        } catch (\Throwable $e) {
            \Log::error('Error in getUsersByDeviceUid', [
                'device_uid' => $deviceUid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return collect();
        }
    }

    public function hasDeviceUidRegistrations(string $deviceUid): bool
    {
        if (!$this->isValidDeviceUid($deviceUid)) {
            return false;
        }

        return DeviceRegistration::where('device_uid', strtolower(trim($deviceUid)))->exists();
    }

    public function refreshCapabilities(DeviceRegistration $device, array $capabilities = []): DeviceRegistration
    {
        return $this->tokenService->persistCapabilities($device, $capabilities);
    }

    public function getTokenService(): DeviceTokenService
    {
        return $this->tokenService;
    }

    protected function refreshExpiredToken(DeviceRegistration $device): ?DeviceRegistration
    {
        if (!$device->is_active) {
            return null;
        }

        $capabilities = $device->capabilities ?? [];
        $this->tokenService->issue($device, $capabilities);
        $device->refresh();

        \Log::info('Device token refreshed after expiration', [
            'device_id' => $device->id,
            'parent_id' => $device->parent_user_id,
            'new_expires_at' => $device->token_expires_at,
        ]);

        return $device;
    }

    protected function refreshExpiringToken(DeviceRegistration $device): ?DeviceRegistration
    {
        if (!$device->is_active) {
            return $device;
        }

        $capabilities = $device->capabilities ?? [];
        $this->tokenService->issue($device, $capabilities);
        $device->refresh();

        \Log::info('Device token proactively refreshed before expiration', [
            'device_id' => $device->id,
            'parent_id' => $device->parent_user_id,
            'new_expires_at' => $device->token_expires_at,
        ]);

        return $device;
    }

    public function refreshDeviceToken(DeviceRegistration $device, array $capabilities = []): DeviceRegistration
    {
        $capabilitiesToUse = !empty($capabilities) ? $capabilities : ($device->capabilities ?? []);
        $this->tokenService->issue($device, $capabilitiesToUse);
        $device->refresh();

        \Log::info('Device token manually refreshed', [
            'device_id' => $device->id,
            'parent_id' => $device->parent_user_id,
            'new_expires_at' => $device->token_expires_at,
            'has_new_capabilities' => !empty($capabilities),
        ]);

        return $device;
    }

    public function isTokenExpired(DeviceRegistration $device): bool
    {
        if (!$device->token_expires_at) {
            return false;
        }

        return now()->greaterThan($device->token_expires_at);
    }
}
