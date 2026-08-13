<?php

namespace App\Services;

use App\Models\DeviceRegistration;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DeviceTokenService
{
    protected string $signingKey;
    protected int $ttlMinutes;

    public function __construct(?int $ttlMinutes = null)
    {
        $appKey = config('app.key');
        if (Str::startsWith($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }
        $this->signingKey = (string) $appKey;
        $this->ttlMinutes = $ttlMinutes ?? (int) config('access.device_token_ttl_minutes', 129600); // 90 days default
    }

    public function issue(DeviceRegistration $device, array $capabilities = [], ?CarbonInterface $expiresAt = null): string
    {
        $tokenId = (string) Str::uuid();
        $expiresAt ??= now()->addMinutes($this->ttlMinutes);

        $normalizedCapabilities = $this->normalizeCapabilities($capabilities);
        $capabilityHash = $this->buildCapabilityHash($normalizedCapabilities);

        $payload = [
            'ver' => 1,
            'rid' => $device->id,
            'tid' => $tokenId,
            'exp' => $expiresAt->getTimestamp(),
            'cap' => $capabilityHash,
        ];

        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = $this->sign($encodedPayload);
        $token = $encodedPayload.'.'.$signature;

        $device->forceFill([
            'device_token' => $tokenId,
            'token_expires_at' => $expiresAt,
            'capabilities' => $normalizedCapabilities ?: null,
            'capability_hash' => $capabilityHash,
        ])->save();

        return $token;
    }

    public function decode(string $token, bool $enforceExpiration = true): ?array
    {
        if (!$this->isSignedToken($token)) {
            return null;
        }

        [$encodedPayload, $providedSignature] = explode('.', $token, 2);
        $expectedSignature = $this->sign($encodedPayload);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (!is_array($payload) || empty($payload['rid']) || empty($payload['tid'])) {
            return null;
        }

        if ($enforceExpiration && !empty($payload['exp']) && now()->timestamp > (int) $payload['exp']) {
            return null;
        }

        return $payload;
    }

    /**
     * Decode a signed token without rejecting expired payloads.
     * Used so validateDevice can apply grace-period refresh.
     */
    public function decodeAllowExpired(string $token): ?array
    {
        return $this->decode($token, false);
    }

    public function isSignedToken(string $token): bool
    {
        return str_contains($token, '.');
    }

    public function normalizeCapabilities(?array $capabilities): array
    {
        if (empty($capabilities) || !is_array($capabilities)) {
            return [];
        }

        $allowedKeys = [
            'touch_support',
            'max_touch_points',
            'hardware_concurrency',
            'device_memory',
            'prefers_reduced_motion',
            'prefers_dark_mode',
            'prefers_high_contrast',
            'has_service_worker',
            'has_indexed_db',
            'has_local_storage',
            'has_session_storage',
            'has_webgl',
            'has_autoplay_inline',
            'pointer_accuracy',
            'connection_type',
            'screen_orientation',
            'timezone_offset',
            'language',
            'platform',
            'screen_width',
            'screen_height',
            'pixel_ratio',
        ];

        $filtered = Arr::only($capabilities, $allowedKeys);

        ksort($filtered);

        return $filtered;
    }

    public function buildCapabilityHash(array $capabilities): ?string
    {
        if (empty($capabilities)) {
            return null;
        }

        return hash('sha256', json_encode($capabilities, JSON_UNESCAPED_SLASHES));
    }

    public function persistCapabilities(DeviceRegistration $device, array $capabilities = []): DeviceRegistration
    {
        $normalized = $this->normalizeCapabilities($capabilities);
        $capabilityHash = $this->buildCapabilityHash($normalized);

        $device->forceFill([
            'capabilities' => $normalized ?: null,
            'capability_hash' => $capabilityHash,
        ])->save();

        return $device->fresh();
    }

    /**
     * Get the current signed token for a device.
     * Reconstructs the token from device data.
     */
    public function getSignedToken(DeviceRegistration $device): ?string
    {
        if (!$device->device_token || !$device->token_expires_at) {
            return null; // Legacy token or no token
        }

        $normalizedCapabilities = $device->capabilities ?? [];
        $capabilityHash = $this->buildCapabilityHash($normalizedCapabilities);

        $payload = [
            'ver' => 1,
            'rid' => $device->id,
            'tid' => $device->device_token,
            'exp' => $device->token_expires_at->getTimestamp(),
            'cap' => $capabilityHash,
        ];

        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = $this->sign($encodedPayload);
        
        return $encodedPayload.'.'.$signature;
    }

    protected function sign(string $encodedPayload): string
    {
        $signature = hash_hmac('sha256', $encodedPayload, $this->signingKey, true);

        return $this->base64UrlEncode($signature);
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}

