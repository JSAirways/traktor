<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class UserLookupService
{
    public function __construct(
        protected DeviceRegistrationService $deviceService
    ) {
    }

    public function findUserBySlug(Request $request, string $slug): ?User
    {
        $device = $this->deviceService->getDeviceFromCookie($request);
        $user = null;

        if ($device && $device->isActive() && $device->parent) {
            if (!$device->relationLoaded('parent')) {
                $device->load('parent');
            }

            $user = User::where('slug', $slug)
                ->where(function ($query) use ($device) {
                    $query->where('id', $device->parent->id)
                        ->orWhere('parent_id', $device->parent->id);
                })
                ->first();
        }

        return $user ?? User::where('slug', $slug)->first();
    }

    public function findUserByProfileName(Request $request, string $profileName): ?User
    {
        $device = $this->deviceService->getDeviceFromCookie($request);
        $user = null;

        if ($device && $device->isActive() && $device->parent) {
            if (!$device->relationLoaded('parent')) {
                $device->load('parent');
            }

            $user = User::where('profile_name', $profileName)
                ->where(function ($query) use ($device) {
                    $query->where('id', $device->parent->id)
                        ->orWhere('parent_id', $device->parent->id);
                })
                ->first();
        }

        return $user ?? User::where('profile_name', $profileName)->first();
    }

    public function findViewableUserBySlug(Request $request, string $slug): ?User
    {
        $device = $this->deviceService->getDeviceFromCookie($request);
        $user = null;

        if ($device && $device->isActive() && $device->parent) {
            if (!$device->relationLoaded('parent')) {
                $device->load('parent');
            }

            $user = User::where('slug', $slug)
                ->where('is_viewable', true)
                ->where(function ($query) use ($device) {
                    $query->where('id', $device->parent->id)
                        ->orWhere('parent_id', $device->parent->id);
                })
                ->first();
        }

        return $user ?? User::where('slug', $slug)
            ->where('is_viewable', true)
            ->first();
    }

    public function findViewableUserByProfileName(Request $request, string $profileName): ?User
    {
        $device = $this->deviceService->getDeviceFromCookie($request);
        $user = null;

        if ($device && $device->isActive() && $device->parent) {
            if (!$device->relationLoaded('parent')) {
                $device->load('parent');
            }

            $user = User::where('profile_name', $profileName)
                ->where('is_viewable', true)
                ->where(function ($query) use ($device) {
                    $query->where('id', $device->parent->id)
                        ->orWhere('parent_id', $device->parent->id);
                })
                ->first();
        }

        return $user ?? User::where('profile_name', $profileName)
            ->where('is_viewable', true)
            ->first();
    }
}


