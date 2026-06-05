@extends('layouts.app')

@section('body-class', 'bg-dark text-light')

@section('content')
<x-layout.navbar variant="frontend">
    <x-slot name="actions">
        @php
            $hasHeaderActions = $__env->hasSection('header-actions');
        @endphp
        {{-- Only include PWA install button in layout if header-actions section is not defined (to avoid duplicates) --}}
        @if(!$hasHeaderActions)
            <x-ui.pwa-install-button />
        @endif
        @if(!request()->routeIs('welcome') && !request()->routeIs('device.register.show') && !request()->routeIs('home'))
            <x-ui.locale-switcher />
        @endif
        <button type="button" class="btn btn-outline-light border-0 d-none" id="welcomeBackBtn" onclick="showUserSelection()">
            <i class="bi bi-chevron-left me-1"></i> {{ __('common.back') }}
        </button>
        @if(request()->routeIs('device.register.show') && ($showBackButton ?? false))
            <a href="{{ route('welcome') }}" class="btn btn-outline-light border-0">
                <i class="bi bi-chevron-left me-1"></i> {{ __('common.back') }}
            </a>
        @endif
        @if(request()->routeIs('register-account'))
            <a href="{{ route('welcome') }}" class="btn btn-outline-light border-0">
                <i class="bi bi-chevron-left me-1"></i> {{ __('common.back') }}
            </a>
        @endif
        @if($hasHeaderActions)
            @yield('header-actions')
        @endif
        {{-- Show cogwheel on welcome page or device registration page (always show, even if device not registered) --}}
        @if(!$hasHeaderActions && (request()->routeIs('welcome') || request()->routeIs('device.register.show')))
            <button type="button" class="btn btn-outline-light border-0" title="{{ __('gallery.settings') }}" data-bs-toggle="offcanvas" data-bs-target="#optionsMenuOffcanvas">
                <i class="bi bi-gear fs-3"></i>
            </button>
        @endif
    </x-slot>
</x-layout.navbar>

<div class="container-fluid welcome-content">
    <div class="container">
        @yield('main-content')
    </div>
</div>

<!-- Password Login Modal - rendered outside container to avoid blur -->
<x-modals.password-login-modal />

@if($hasRegisteredDevice ?? false)
    <x-modals.admin-password-modal />
@endif

{{-- Pending Approval Modal - rendered outside container to avoid blur --}}
@if(request()->routeIs('welcome'))
    <x-modals.pending-approval-modal />
@endif

{{-- Options menu - always show on frontend pages --}}
@if(request()->routeIs('welcome'))
    {{-- Welcome page: show register account, hide logout device and admin button --}}
    <x-ui.options-menu-offcanvas 
        variant="dark" 
        :show-profile-selection="false"
        :show-register-account="true"
        :show-logout-device="false"
        :show-admin-button="false"
    />
@elseif(request()->routeIs('device.register.show'))
    {{-- Device registration page: show register account, logout device, and admin button only if logged in --}}
    <x-ui.options-menu-offcanvas 
        variant="dark" 
        :show-profile-selection="false"
        :show-register-account="true"
        :show-logout-device="auth()->check()"
        :show-admin-button="auth()->check()"
    />
@elseif(request()->routeIs('home'))
    {{-- Profile selection page (home route): show logout device and admin button when device is registered, hide register account --}}
    {{-- Note: hasRegisteredDevice is set by DeviceComposer and/or controller --}}
    <x-ui.options-menu-offcanvas 
        variant="dark" 
        :show-profile-selection="false"
        :show-register-account="false"
        :show-logout-device="($hasRegisteredDevice ?? false) || (isset($device) && $device && $device->isActive())"
        :show-admin-button="($hasRegisteredDevice ?? false) || (isset($device) && $device && $device->isActive())"
    />
@elseif($hasRegisteredDevice ?? false)
    {{-- Other pages with registered device: standard options, but only show logout/admin if logged in --}}
    <x-ui.options-menu-offcanvas 
        variant="dark" 
        :show-profile-selection="false"
        :show-register-account="false"
        :show-logout-device="auth()->check()"
        :show-admin-button="auth()->check()"
    />
@endif
@endsection

