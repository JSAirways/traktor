<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceChildVisibility;
use App\Models\DeviceRegistration;
use App\Models\User;
use App\Services\DeviceRegistrationService;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(
        protected DeviceRegistrationService $deviceService
    ) {
    }

    /**
     * List all registered devices.
     */
    public function index(Request $request)
    {
        // Check admin access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        $userFilter = $request->get('user_id');
        
        // Only fetch parent users who have devices, eager load their devices and children
        $query = User::whereNull('parent_id')
            ->whereHas('deviceRegistrations')
            ->with([
                'deviceRegistrations' => function ($query) {
                    $query->with('childVisibility.child')
                        ->orderBy('last_used_at', 'desc')
                        ->orderBy('registered_at', 'desc');
                },
                'children'
            ])
            ->orderBy('username');
        
        if ($userFilter) {
            $query->where('id', $userFilter);
        }
        
        $parents = $query->paginate(20)->withQueryString();

        // Get all parents for filter dropdown
        $allParents = User::whereNull('parent_id')
            ->whereHas('deviceRegistrations')
            ->orderBy('username')
            ->get();

        // Resolve current device from cookie so we can highlight it in the UI
        $currentDevice = $this->deviceService->getDeviceFromCookie($request);
        $currentDeviceToken = $currentDevice?->device_token;

        return view('admin.devices.index', compact('parents', 'allParents', 'userFilter', 'currentDeviceToken'));
    }

    /**
     * Show device details and manage child visibility.
     */
    public function show(DeviceRegistration $device, Request $request)
    {
        // Check admin access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        $device->load(['parent', 'childVisibility.child']);
        
        // Get all children of the parent
        $parent = $device->parent;
        $allChildren = $parent->children()->orderBy('username')->get();
        
        // Get currently visible children
        $visibleChildren = $device->childVisibility()
            ->where('is_visible', true)
            ->with('child')
            ->get()
            ->pluck('child_user_id')
            ->toArray();
        
        $currentDevice = $this->deviceService->getDeviceFromCookie($request);
        $currentDeviceToken = $currentDevice?->device_token;

        return view('admin.devices.show', compact('device', 'allChildren', 'visibleChildren', 'currentDeviceToken'));
    }

    /**
     * Update child visibility for a device.
     */
    public function updateChildVisibility(DeviceRegistration $device, Request $request)
    {
        // Check admin access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        $request->validate([
            'child_ids' => 'nullable|array',
            'child_ids.*' => 'exists:users,id',
        ]);

        // Get all children of the device's parent
        $parent = $device->parent;
        $validChildIds = $parent->children()->pluck('id')->toArray();
        
        // Ensure only valid children are processed
        $childIds = $request->get('child_ids', []);
        $validIds = array_intersect($childIds, $validChildIds);

        // Update visibility for all children
        foreach ($validChildIds as $childId) {
            $visibility = DeviceChildVisibility::firstOrCreate(
                [
                    'device_registration_id' => $device->id,
                    'child_user_id' => $childId,
                ],
                ['is_visible' => false]
            );
            
            $visibility->update([
                'is_visible' => in_array($childId, $validIds),
            ]);
        }

        return back()->with('success', __('messages.child_visibility_updated_success'));
    }

    /**
     * Deactivate a device.
     */
    public function deactivate(DeviceRegistration $device)
    {
        // Check admin access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        $device->deactivate();
        
        return back()->with('success', __('messages.device_deactivated'));
    }

    /**
     * Activate a device.
     */
    public function activate(DeviceRegistration $device)
    {
        // Check admin access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        $device->activate();
        
        return back()->with('success', __('messages.device_activated'));
    }

    /**
     * Delete a device registration.
     */
    public function destroy(DeviceRegistration $device, Request $request)
    {
        // Check admin access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        // Check if this is the current device (the one the admin is logged in from)
        $isCurrentDevice = $this->deviceService->isCurrentDevice($device, $request);
        
        // Store device fingerprint before deletion to check for other registrations
        $deviceFingerprint = $device->device_fingerprint;
        
        // If this is the current device, check for other devices BEFORE deleting
        // This ensures we can use the authenticated session for the check
        $hasOtherDevices = false;
        if ($isCurrentDevice && $deviceFingerprint) {
            $hasOtherDevices = DeviceRegistration::where('device_fingerprint', $deviceFingerprint)
                ->where('is_active', true)
                ->where('id', '!=', $device->id) // Exclude the device we're about to delete
                ->exists();
        }
        
        // Delete the device
        $device->delete();
        
        // If this was the current device, clear cookies and logout
        if ($isCurrentDevice) {
            // Clear device cookies first
            $this->deviceService->clearDeviceCookie();
            
            // Logout and regenerate session (regenerate() handles both session and token)
            auth()->logout();
            $request->session()->regenerate(true); // true = delete old session
            
            // Determine redirect target
            $redirectRoute = $hasOtherDevices ? 'welcome' : 'device.register.show';
            $redirectMessage = $hasOtherDevices 
                ? __('messages.device_deleted_logged_out')
                : __('messages.device_deleted_please_register');
            
            // Redirect with new session
            return redirect()->route($redirectRoute)
                ->with('success', $redirectMessage);
        }
        
        return redirect()->route('admin.devices.index')
            ->with('success', __('messages.device_registration_deleted'));
    }
}
