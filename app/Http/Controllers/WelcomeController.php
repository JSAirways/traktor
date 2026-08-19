<?php

namespace App\Http\Controllers;

use App\Services\DeviceRegistrationService;
use App\Services\ProfilePictureService;
use App\Services\ViewingSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    public function __construct(
        protected DeviceRegistrationService $deviceService,
        protected ProfilePictureService $profilePictureService,
        protected ViewingSessionService $viewingSessionService
    ) {
    }

    /**
     * Get device and children data for registered devices.
     * 
     * @return array{0: \App\Models\DeviceRegistration|null, 1: \App\Models\User|null, 2: \Illuminate\Database\Eloquent\Collection|null}
     */
    protected function getDeviceAndChildren(Request $request): array
    {
        $device = $this->deviceService->getDeviceFromCookie($request);
        
        if (!$device || !$device->isActive()) {
            return [null, null, null];
        }
        
        $parent = $device->parent;
        $visibleChildIds = $device->childVisibility()
            ->where('is_visible', true)
            ->pluck('child_user_id')
            ->toArray();
        
        // If visibility settings exist, use them; otherwise show all viewable children
        if (!empty($visibleChildIds)) {
            $children = $parent->children()
                ->whereIn('id', $visibleChildIds)
                ->where('is_viewable', true)
                ->orderBy('username')
                ->get();
        } else {
            $children = $parent->children()
                ->where('is_viewable', true)
                ->orderBy('username')
                ->get();
        }
        
        // If parent has opted to appear in profile selection, add them to the collection
        if ($parent->appears_in_profile_selection && $parent->is_viewable) {
            $children->push($parent);
        }
        
        // Touch device last used
        $this->deviceService->touchDevice($device);
        
        return [$device, $parent, $children];
    }

    /**
     * Home page (profile selection) - shows profile selection if device registered or user is authenticated
     * Clears viewing session when returning to profile selection (user is switching profiles)
     */
    public function home(Request $request)
    {
        // Clear viewing session when returning to home page (profile selection)
        // This allows user to switch to a different profile
        $this->viewingSessionService->clearSession($request);
        
        // Get requested slug from query parameter (if user was trying to access a specific profile)
        $requestedSlug = $request->query('requested_slug') ?? $request->session()->get('requested_slug');
        $intendedUrl = $request->query('intended');
        
        // Decode the intended URL if it was encoded
        if ($intendedUrl) {
            $intendedUrl = urldecode($intendedUrl);
        }
        
        // Check if device is registered and active
        [$device, $parent, $children] = $this->getDeviceAndChildren($request);
        
        // If user is authenticated, show their profile selection even without device
        if (Auth::check()) {
            $user = Auth::user();
            // Get user's children for profile selection
            $userChildren = $user->children()
                ->where('is_viewable', true)
                ->orderBy('username')
                ->get();
            
            // If user has opted to appear in profile selection, add them to the collection
            if ($user->appears_in_profile_selection && $user->is_viewable) {
                $userChildren->push($user);
            }
            
            return view('profiles.selection', [
                'parent' => $user,
                'children' => $userChildren,
                'device' => $device, // May be null if device not registered
                'hasRegisteredDevice' => $device !== null && $device->isActive(), // Required for admin password modal script
                'requestedSlug' => $requestedSlug, // Pass to view
                'intendedUrl' => $intendedUrl, // Pass to view
            ]);
        }
        
        if ($device && $parent && $children !== null) {
            // Device is registered and active - show profile selection page
            return view('profiles.selection', [
                'parent' => $parent,
                'children' => $children,
                'device' => $device,
                'hasRegisteredDevice' => true, // Required for admin password modal script
                'requestedSlug' => $requestedSlug, // Pass to view
                'intendedUrl' => $intendedUrl, // Pass to view
            ]);
        }

        // Device not registered or not active, and user not authenticated - redirect to welcome page
        return redirect()->route('welcome');
    }

    /**
     * Welcome page (user selection) - shown when device not registered
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function welcome(Request $request)
    {
        // If user is already logged in, redirect to backend
        if (auth()->check()) {
            return redirect()->route('admin.dashboard');
        }

        // Check if durable device_uid was ever registered (even if logged out)
        $deviceUid = $request->input('device_uid')
            ?? $this->deviceService->getDeviceUidFromCookie($request);
        
        $hasPreviousRegistrations = false;
        if ($deviceUid) {
            $hasPreviousRegistrations = $this->deviceService->hasDeviceUidRegistrations($deviceUid);
        }

        // Get available profile pictures from profile-pictures/cats for random selection
        $catGifs = $this->profilePictureService->getPicturesByCategory('cats');

        // Show user selection or registration option
        // JavaScript will handle checking device_uid and listing known accounts
        return view('welcome.index', [
            'catGifs' => $catGifs,
            'hasPreviousRegistrations' => $hasPreviousRegistrations,
        ]);
    }

    /**
     * Verify admin password and redirect to admin dashboard.
     * Used when accessing admin panel from registered device (no login required).
     * 
     * Solution 3: Decoupled from device token - can authenticate with email/password even if device token is expired.
     */
    public function verifyAdminPassword(Request $request)
    {
        $request->validate([
            'password' => 'nullable|string|required_without:pin',
            'pin' => ['nullable', 'string', 'size:4', 'regex:/^[0-9]{4}$/', 'required_without:password'],
            'email' => 'nullable|string|email', // Optional - will try device if not provided
        ]);

        $pin = $request->input('pin');
        $password = $request->input('password');
        $usingPin = !empty($pin);

        // Check if user is already authenticated (from backend)
        $user = Auth::user();
        if ($usingPin) {
            $user = null;
        }
        
        // Solution 3: If not authenticated, try email/password authentication first
        if (!$usingPin && !$user && $request->filled('email')) {
            $credentials = [
                'email' => $request->email,
                'password' => $password,
            ];
            
            if (Auth::attempt($credentials)) {
                $user = Auth::user();
                
                // If device exists for this user, refresh its token
                $device = $this->deviceService->getDeviceFromCookie($request);
                if ($device && $device->parent_user_id === $user->id) {
                    // Refresh token for this device (may have been expired)
                    $this->deviceService->refreshDeviceToken($device);
                }
            }
        }
        
        // Fallback: If still not authenticated, try device-based authentication
        if (!$user) {
            $device = $this->deviceService->getDeviceFromCookie($request);
            
            if (!$device || !$device->isActive()) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->error(__('messages.device_not_registered'), ['password' => [__('messages.device_not_registered')]], 422);
                }
                return redirect()->back()
                    ->withErrors(['password' => __('messages.device_not_registered')]);
            }

            $user = $device->parent;

            if (!$user) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->error(__('messages.user_not_found'), ['password' => [__('messages.user_not_found')]], 422);
                }
                return redirect()->back()
                    ->withErrors(['password' => __('messages.user_not_found')]);
            }
            
            if ($usingPin) {
                if (!$user->hasAdminPin() || !$user->verifyAdminPin($pin)) {
                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->error(__('auth.invalid_pin'), ['pin' => [__('auth.invalid_pin')]], 422);
                    }
                    return redirect()->back()
                        ->withErrors(['pin' => __('auth.invalid_pin')]);
                }
            } else {
                // Verify password for device-based authentication
                if (!\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->error(__('messages.invalid_password'), ['password' => [__('messages.invalid_password')]], 422);
                    }
                    return redirect()->back()
                        ->withErrors(['password' => __('messages.invalid_password')]);
                }
            }
            
            // If device token was expired, refresh it
            if ($this->deviceService->isTokenExpired($device)) {
                $this->deviceService->refreshDeviceToken($device);
            }
        } elseif (!$usingPin) {
            // User authenticated via email/password - verify password was correct
            if (!\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
                Auth::logout(); // Logout if password was wrong
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->error(__('messages.invalid_password'), ['password' => [__('messages.invalid_password')]], 422);
                }
                return redirect()->back()
                    ->withErrors(['password' => __('messages.invalid_password')]);
            }
        }

        // Password is correct - authenticate user (if not already) and redirect to admin dashboard
        if (!Auth::check()) {
            Auth::login($user);
        }
        
        // Regenerate session to prevent CSRF token mismatch
        // Note: Viewing session is now stored in device registration, so regeneration won't affect it
        $request->session()->regenerate();
        
        if ($request->expectsJson() || $request->ajax()) {
            return response()->success([
                'redirect' => route('admin.dashboard'),
                'csrf_token' => csrf_token() // Include new CSRF token in response
            ], __('auth.login_successful'));
        }
        
        return redirect()->route('admin.dashboard');
    }
}
