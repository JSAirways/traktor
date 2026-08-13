<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Constants\DeviceConstants;
use App\Http\Requests\DeviceRegistrationRequest;
use App\Models\DeviceChildVisibility;
use App\Services\DeviceRegistrationService;
use App\Services\ProfilePictureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceController extends Controller
{
    public function __construct(
        protected DeviceRegistrationService $deviceService,
        protected ProfilePictureService $profilePictureService
    ) {
    }

    /**
     * Show device registration form page.
     */
    public function showRegistrationForm(Request $request)
    {
        // If user is already logged in, redirect to backend
        if (auth()->check()) {
            return redirect()->route('admin.dashboard');
        }

        // Always show back button to welcome page - user should be able to navigate back
        $showBackButton = true;

        // Get profile pictures from profile-pictures/cats for profile picture selection
        $catGifs = $this->profilePictureService->getPicturesByCategory('cats');

        return view('devices.register', [
            'catGifs' => $catGifs,
            'showBackButton' => $showBackButton,
        ]);
    }

    /**
     * Handle device registration (requires parent login).
     */
    public function register(DeviceRegistrationRequest $request)
    {
        // Check if this is a password-only login (device_name is the password-only login flag)
        $isPasswordOnlyLogin = trim($request->device_name ?? '') === DeviceConstants::PASSWORD_ONLY_LOGIN_FLAG;

        // Attempt to authenticate parent
        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
        ];

        if (!Auth::attempt($credentials)) {
            // Check for AJAX/JSON request using multiple methods for compatibility
            $isAjaxRequest = $request->wantsJson() 
                || $request->ajax() 
                || $request->header('X-Requested-With') === 'XMLHttpRequest'
                || $request->header('Accept') === 'application/json';
                
            if ($isPasswordOnlyLogin) {
                // Password-only login error - check if AJAX request
                if ($isAjaxRequest) {
                    return response()->error(
                        __('auth.invalid_credentials'),
                        ['password' => [__('auth.invalid_credentials')]],
                        422
                    );
                }
                
                // Password-only login error - redirect back to welcome page
                return redirect()->route('welcome')
                    ->withInput($request->only('email'))
                    ->withErrors([
                        'password' => __('auth.invalid_credentials')
                    ]);
            } else {
                // Registration form error - check if AJAX request
                if ($isAjaxRequest) {
                    return response()->error(
                        __('auth.invalid_credentials'),
                        [
                            'username' => [__('auth.invalid_credentials')],
                            'password' => [__('auth.invalid_credentials')]
                        ],
                        422
                    );
                }
                
                // Registration form error - redirect back to registration page
                return redirect()->route('device.register.show')
                    ->withInput($request->only('email', 'device_name', 'device_uid', 'user_agent', 'screen_resolution'))
                    ->withErrors([
                        'email' => __('auth.invalid_credentials'),
                        'password' => __('auth.invalid_credentials')
                    ]);
            }
        }

        $parent = Auth::user();

        // Check if account is approved
        if (!$parent->isApproved()) {
            Auth::logout();
            return redirect()->route('pending-approval');
        }

        // Prefer HttpOnly cookie device_uid over client-supplied value (PS4 / fixation safety)
        $deviceUid = $this->deviceService->resolveDeviceUidFromRequest($request, $request->input('device_uid'));
        $capabilities = $this->extractCapabilities($request);

        // Check if THIS user already registered this device (prevent duplicate registration under same account)
        // Note: Different users CAN register the same device_uid - only prevent same user registering twice
        if (!$isPasswordOnlyLogin) {
            $thisUserDevice = \App\Models\DeviceRegistration::where('parent_user_id', $parent->id)
                ->where('device_uid', $deviceUid)
                ->orderBy('last_used_at', 'desc')
                ->orderBy('registered_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            
            if ($thisUserDevice) {
                // Solution 2: Check if token is expired - allow re-registration if expired
                $isExpired = $this->deviceService->isTokenExpired($thisUserDevice);
                
                if (!$isExpired) {
                    // Device already registered with valid token - prevent duplicate registration
                    // User must use password login form to access the device, not register again
                    
                    // Store email, username, and device name before logout (needed for error message)
                    $email = $parent->email;
                    $username = $parent->username;
                    $deviceName = $thisUserDevice->device_name ?? DeviceConstants::DEFAULT_DEVICE_NAME;
                    
                    // Log the duplicate attempt for debugging
                    \Log::warning('Duplicate device registration attempt prevented (token still valid)', [
                        'parent_id' => $parent->id,
                        'device_id' => $thisUserDevice->id,
                        'device_uid' => $deviceUid,
                        'user_agent' => $request->user_agent,
                        'screen_resolution' => $request->screen_resolution,
                        'token_expires_at' => $thisUserDevice->token_expires_at,
                    ]);
                    
                    // Logout and regenerate session to ensure clean state
                    Auth::logout();
                    $request->session()->regenerate(true); // true = delete old session
                    
                    // Redirect to welcome page with duplicate device error
                    return redirect()->route('welcome')
                        ->with('device_duplicate_error', [
                            'message' => __('messages.device_already_registered'),
                            'email' => $email,
                            'username' => $username,
                            'device_name' => $deviceName,
                        ]);
                }
                
                // Token is expired - allow re-registration (will reactivate device and issue new token)
                \Log::info('Allowing re-registration of device with expired token', [
                    'parent_id' => $parent->id,
                    'device_id' => $thisUserDevice->id,
                    'device_uid' => $deviceUid,
                    'token_expired_at' => $thisUserDevice->token_expires_at,
                    'expired_days_ago' => $thisUserDevice->token_expires_at ? now()->diffInDays($thisUserDevice->token_expires_at) : null,
                ]);
            }
        }

        // Check if device was reactivated or newly created
        $wasReactivated = \App\Models\DeviceRegistration::where('parent_user_id', $parent->id)
            ->where('device_uid', $deviceUid)
            ->where('is_active', false)
            ->exists();

        // Register device
        [$device, $signedToken] = $this->deviceService->registerDevice($parent, [
            'device_name' => $request->device_name,
            'device_uid' => $deviceUid,
            'user_agent' => $request->user_agent,
            'screen_resolution' => $request->screen_resolution,
            'capabilities' => $capabilities,
        ]);

        // Initialize child visibility - make all viewable children visible by default
        $viewableChildren = $parent->children()->where('is_viewable', true)->get();
        foreach ($viewableChildren as $child) {
            DeviceChildVisibility::firstOrCreate(
                [
                    'device_registration_id' => $device->id,
                    'child_user_id' => $child->id,
                ],
                ['is_visible' => true]
            );
        }

        // Logout the authenticated session (we only need device cookie)
        Auth::logout();

        $successMessage = $wasReactivated 
            ? __('messages.device_reactivated')
            : __('messages.device_registered');

        [$tokenCookie, $parentCookie, $uidCookie] = $this->deviceService->makeDeviceCookies($device, $signedToken);

        // Check if AJAX request - return JSON with redirect URL
        // Check for AJAX/JSON request using multiple methods for compatibility
        $isAjaxRequest = $request->wantsJson() 
            || $request->ajax() 
            || $request->header('X-Requested-With') === 'XMLHttpRequest'
            || $request->header('Accept') === 'application/json';
            
        if ($isAjaxRequest) {
            return response()->success(
                [
                    'redirect' => url('/'),
                    'device_uid' => $device->device_uid,
                ],
                $successMessage,
                200
            )
            ->withCookie($tokenCookie)
            ->withCookie($parentCookie)
            ->withCookie($uidCookie);
        }

        // For non-AJAX requests, set cookies and redirect
        return redirect()->route('home')
            ->with('success', $successMessage)
            ->withCookie($tokenCookie)
            ->withCookie($parentCookie)
            ->withCookie($uidCookie);
    }

    /**
     * Logout device (clear device registration).
     */
    public function logout(Request $request)
    {
        $deviceToken = $request->cookie('device_token');
        
        if ($deviceToken) {
            $this->deviceService->logoutDevice($deviceToken);
        }

        // Clear device cookies
        $this->deviceService->clearDeviceCookie();

        // Clear viewing session
        session()->forget(['viewing_slug', 'viewing_validated_at']);

        // Log out the authenticated user session so they can see the welcome page with user selection
        auth()->logout();
        // Use regenerate() instead of invalidate() + regenerateToken() for proper CSRF token handling
        $request->session()->regenerate(true); // true = delete old session

        return redirect()->route('welcome')
            ->with('success', __('messages.device_logged_out'));
    }

    /**
     * Get registered users for a durable device_uid (API endpoint).
     * Falls back to device_uid cookie when body is empty (PS4 without localStorage).
     */
    public function getRegisteredUsers(Request $request)
    {
        try {
            $request->validate([
                'device_uid' => 'nullable|uuid',
            ]);

            $deviceUid = $this->deviceService->resolveDiscoveryDeviceUid(
                $request,
                $request->input('device_uid')
            );

            if (!$deviceUid) {
                return response()->json([]);
            }

            $devices = $this->deviceService->getUsersByDeviceUid($deviceUid);

            // Map devices to user data - service already filters out null parents
            $users = $devices->map(function ($device) {
                try {
                    // Double-check parent exists (defensive programming)
                    if (!$device || !$device->parent) {
                        return null;
                    }
                    
                    $parent = $device->parent;
                    $profilePicture = $parent->profile_picture ?? $parent->cat_gif ?? null;
                    
                    // Ensure we have required fields
                    $email = $parent->email ?? '';
                    $username = $parent->username ?? '';
                    
                    if (empty($email) || empty($username)) {
                        \Log::warning('Device has parent but missing email or username', [
                            'device_id' => $device->id,
                            'parent_id' => $device->parent_user_id
                        ]);
                        return null;
                    }
                    
                    return [
                        'email' => $email,
                        'username' => $username,
                        'device_name' => $device->device_name ?? DeviceConstants::DEFAULT_DEVICE_NAME,
                        'parent_id' => $device->parent_user_id,
                        'profile_picture' => $profilePicture,
                    ];
                } catch (\Throwable $e) {
                    // Log error but continue processing other devices
                    \Log::warning('Error processing device in getRegisteredUsers', [
                        'device_id' => $device->id ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return null;
                }
            })->filter(function ($user) {
                return $user !== null && !empty($user['email']) && !empty($user['username']);
            });

            return response()->json($users->values()->toArray());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Invalid request'], 422);
        } catch (\Throwable $e) {
            \Log::error('Error in getRegisteredUsers', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Refresh persisted capability data for the current device.
     * Solution 5: Automatically issues new token when capability hash changes.
     */
    public function refreshCapabilities(Request $request)
    {
        $device = $this->deviceService->getDeviceFromCookie($request);

        if (!$device || !$device->isActive()) {
            return response()->error(
                __('messages.device_not_registered'),
                null,
                401
            );
        }

        $data = $request->validate([
            'capabilities' => 'required|array',
        ]);

        // Normalize and hash new capabilities
        $normalizedCapabilities = $this->deviceService->getTokenService()->normalizeCapabilities($data['capabilities']);
        $newCapabilityHash = $this->deviceService->getTokenService()->buildCapabilityHash($normalizedCapabilities);
        
        // Check if capability hash has changed
        $capabilityHashChanged = $device->capability_hash !== $newCapabilityHash;
        
        // Update capabilities
        $updatedDevice = $this->deviceService->refreshCapabilities($device, $data['capabilities']);
        
        // Solution 5: If capability hash changed, issue new token automatically
        $tokenRefreshed = false;
        if ($capabilityHashChanged) {
            \Log::info('Capability hash changed, automatically refreshing device token', [
                'device_id' => $device->id,
                'parent_id' => $device->parent_user_id,
                'old_hash' => $device->capability_hash,
                'new_hash' => $newCapabilityHash,
            ]);
            
            $updatedDevice = $this->deviceService->refreshDeviceToken($updatedDevice, $normalizedCapabilities);
            $tokenRefreshed = true;
        }

        return response()->success([
            'device_id' => $updatedDevice->id,
            'capability_hash' => $updatedDevice->capability_hash,
            'token_refreshed' => $tokenRefreshed,
        ]);
    }

    /**
     * Normalize capability payloads coming from the client.
     */
    protected function extractCapabilities(Request $request): array
    {
        $raw = $request->input('capabilities');

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $decoded = null;
            }

            if (is_array($decoded)) {
                // Capability payloads are associative objects, not lists
                return $decoded;
            }
        }

        return [];
    }
}
