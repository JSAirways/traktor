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

    public function findUserByUsername(Request $request, string $username): ?User
    {
        $device = $this->deviceService->getDeviceFromCookie($request);
        $user = null;

        if ($device && $device->isActive() && $device->parent) {
            if (!$device->relationLoaded('parent')) {
                $device->load('parent');
            }

            $user = User::where('username', $username)
                ->where(function ($query) use ($device) {
                    $query->where('id', $device->parent->id)
                        ->orWhere('parent_id', $device->parent->id);
                })
                ->first();
        }

        return $user ?? User::where('username', $username)->first();
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

    public function findViewableUserByUsername(Request $request, string $username): ?User
    {
        $device = $this->deviceService->getDeviceFromCookie($request);
        $user = null;

        if ($device && $device->isActive() && $device->parent) {
            if (!$device->relationLoaded('parent')) {
                $device->load('parent');
            }

            $user = User::where('username', $username)
                ->where('is_viewable', true)
                ->where(function ($query) use ($device) {
                    $query->where('id', $device->parent->id)
                        ->orWhere('parent_id', $device->parent->id);
                })
                ->first();
        }

        return $user ?? User::where('username', $username)
            ->where('is_viewable', true)
            ->first();
    }
}


