<?php

namespace App\View\Composers;

use App\Services\DeviceRegistrationService;
use Illuminate\View\View;

class DeviceComposer
{
    protected DeviceRegistrationService $deviceService;

    public function __construct(DeviceRegistrationService $deviceService)
    {
        $this->deviceService = $deviceService;
    }

    /**
     * Bind data to the view.
     */
    public function compose(View $view)
    {
        $request = request();
        $device = $this->deviceService->getDeviceFromCookie($request);
        
        $parent = null;
        if ($device && $device->isActive()) {
            $parent = $device->parent;
        }
        
        $view->with('device', $device);
        $view->with('hasRegisteredDevice', $device && $device->isActive());
        $view->with('parentUser', $parent);
        $view->with('deviceNeedsCapabilityRefresh', $device && empty($device->capabilities));
    }
}

