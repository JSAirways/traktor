<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceChildVisibility;
use App\Models\DeviceRegistration;
use App\Services\DeviceRegistrationService;
use Illuminate\Http\Request;

class ParentDeviceController extends Controller
{
    public function __construct(
        protected DeviceRegistrationService $deviceService
    ) {
    }

    /**
     * List all devices registered by the current parent.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $devices = DeviceRegistration::where('parent_user_id', $user->id)
            ->with(['childVisibility.child'])
            ->orderBy('last_used_at', 'desc')
            ->orderBy('registered_at', 'desc')
            ->get();

        $currentDevice = $this->deviceService->getDeviceFromCookie($request);
        $currentDeviceToken = $currentDevice?->device_token;

        return view('admin.parent-devices.index', compact('devices', 'currentDeviceToken'));
    }

    /**
     * Show device details and manage child visibility.
     */
    public function show(DeviceRegistration $device)
    {
        $user = auth()->user();
        
        // Ensure this device belongs to the current user
        if ($device->parent_user_id !== $user->id) {
            abort(403, __('messages.unauthorized_action'));
        }
        
        $device->load(['parent', 'childVisibility.child']);
        
        // Get all children of the parent
        $allChildren = $user->children()->orderBy('username')->get();
        
        // Get current visibility settings
        $currentVisibility = $device->childVisibility->keyBy('child_user_id')->map->is_visible;
        
        return view('admin.parent-devices.show', compact('device', 'allChildren', 'currentVisibility'));
    }

    /**
     * Update device name.
     */
    public function update(DeviceRegistration $device, Request $request)
    {
        $user = auth()->user();
        
        // Ensure this device belongs to the current user
        if ($device->parent_user_id !== $user->id) {
            abort(403, __('messages.unauthorized_action'));
        }
        
        $request->validate([
            'device_name' => 'required|string|max:255',
        ]);
        
        $device->update([
            'device_name' => $request->device_name,
        ]);
        
        return back()->with('success', __('messages.device_name_updated'));
    }

    /**
     * Update child visibility for a device.
     */
    public function updateChildVisibility(DeviceRegistration $device, Request $request)
    {
        $user = auth()->user();
        
        // Ensure this device belongs to the current user
        if ($device->parent_user_id !== $user->id) {
            abort(403, __('messages.unauthorized_action'));
        }
        
        $request->validate([
            'child_visibility' => 'array',
            'child_visibility.*' => 'boolean',
        ]);

        // Get all children of the parent
        $parent = $device->parent;
        $validChildIds = $parent->children()->pluck('id')->toArray();
        
        $updatedCount = 0;

        // Update visibility for all children
        foreach ($validChildIds as $childId) {
            $isVisible = $request->has("child_visibility.{$childId}") && $request->boolean("child_visibility.{$childId}");

            DeviceChildVisibility::updateOrCreate(
                [
                    'device_registration_id' => $device->id,
                    'child_user_id' => $childId,
                ],
                ['is_visible' => $isVisible]
            );
            $updatedCount++;
        }

        return back()->with('success', __('messages.child_visibility_updated', ['count' => $updatedCount]));
    }

    /**
     * Logout (deactivate) a device registration.
     */
    public function logout(DeviceRegistration $device, Request $request)
    {
        $user = auth()->user();
        
        // Ensure this device belongs to the current user
        if ($device->parent_user_id !== $user->id) {
            abort(403, __('messages.unauthorized_action'));
        }
        
        // Check if this is the current device (the one the user is logged in from)
        $isCurrentDevice = $this->deviceService->isCurrentDevice($device, $request);
        
        // Deactivate the device (logout without deleting)
        $device->deactivate();
        
        // Clear device cookies if this is the current device
        if ($isCurrentDevice) {
            $this->deviceService->clearDeviceCookie();
            
            // If this is the current device, log out the user session too
            // so they can see the welcome page with user selection
            auth()->logout();
            $request->session()->regenerate(true); // true = delete old session
            
            return redirect()->route('welcome')
                ->with('success', __('messages.device_logged_out_can_reregister'));
        }

        return redirect()->route('admin.parent-devices.index')
            ->with('success', __('messages.device_logged_out_can_reregister'));
    }

    /**
     * Delete a device registration.
     */
    public function destroy(DeviceRegistration $device, Request $request)
    {
        $user = auth()->user();
        
        // Ensure this device belongs to the current user
        if ($device->parent_user_id !== $user->id) {
            abort(403, __('messages.unauthorized_action'));
        }
        
        // Check if this is the current device (the one the user is logged in from)
        $isCurrentDevice = $this->deviceService->isCurrentDevice($device, $request);
        
        // Store device_uid before deletion to check for other registrations
        $deviceUid = $device->device_uid;
        
        // If this is the current device, check for other devices BEFORE deleting
        // This ensures we can use the authenticated session for the check
        $hasOtherDevices = false;
        if ($isCurrentDevice && $deviceUid) {
            $hasOtherDevices = DeviceRegistration::where('device_uid', $deviceUid)
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

        return redirect()->route('admin.parent-devices.index')
            ->with('success', __('messages.device_deleted'));
    }
}

