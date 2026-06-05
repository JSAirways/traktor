<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - {{ $title ?? __('admin.dashboard') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    {{-- Asset Version for Cache Invalidation --}}
    @php
        $assetVersion = \App\Models\Setting::where('key', 'asset_version')->value('value') ?? '0';
    @endphp
    <meta name="asset-version" content="{{ $assetVersion }}">
    @vite(['resources/css/app.scss', 'resources/js/app.js', 'resources/js/admin/shared/admin-forms.js', 'resources/js/admin/shared/admin-layout.js'])
    @stack('styles')
</head>
<body class="bg-light admin-layout">
    <x-layout.navbar 
        variant="admin" 
        :showSidebarToggle="true"
    >
        <x-slot name="actions">
            <x-ui.pwa-install-button />
            <x-ui.locale-switcher />
            @auth
                {{-- Profile Selection button - always visible --}}
                <button type="button" id="adminProfileSelectionBtn" class="btn border-0 p-0 navbar-profile-picture-btn" title="{{ __('gallery.profile_selection') }}">
                    <x-ui.user-avatar 
                        variant="tile" 
                        :user="auth()->user()" 
                        size="small" 
                        :show-name="false" 
                        mb="mb-0"
                        border-color="light"
                        :icon="auth()->user() ? null : 'bi-person-circle'"
                    />
                </button>
                {{-- Settings button - opens offcanvas menu --}}
                <button type="button" class="btn btn-outline-light border-0" title="{{ __('gallery.settings') }}" data-bs-toggle="offcanvas" data-bs-target="#optionsMenuOffcanvas">
                    <i class="bi bi-gear fs-3"></i>
                </button>
            @endauth
        </x-slot>
    </x-layout.navbar>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar - Hidden on mobile, shown via offcanvas -->
            <div class="col-12 col-md-4 col-lg-3 bg-white border-end p-3 d-none d-md-block">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                            <i class="bi bi-bar-chart me-2"></i>
                            {{ __('admin.dashboard') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.content.*') ? 'active' : '' }}" href="{{ route('admin.content.index') }}">
                            <i class="bi bi-grid me-2"></i>
                            {{ __('admin.content') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.profile.*') ? 'active' : '' }}" href="{{ route('admin.profile.edit') }}">
                            <i class="bi bi-person me-2"></i>
                            {{ __('admin.my_profile') }}
                        </a>
                    </li>
                    @if(auth()->user()->children()->count() > 0 || auth()->user()->role === 'user')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.children.*') ? 'active' : '' }}" href="{{ route('admin.children.index') }}">
                                <i class="bi bi-person-arms-up me-2"></i>
                                {{ __('admin.my_children') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.parent-devices.*') ? 'active' : '' }}" href="{{ route('admin.parent-devices.index') }}">
                                <i class="bi bi-display me-2"></i>
                                {{ __('admin.my_devices') }}
                            </a>
                        </li>
                    @endif
                    @if(auth()->check() && auth()->user()->isAdmin())
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.users.*') && !request()->routeIs('admin.profile.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                                <i class="bi bi-people me-2"></i>
                                {{ __('admin.users') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.devices.*') && !request()->routeIs('admin.parent-devices.*') ? 'active' : '' }}" href="{{ route('admin.devices.index') }}">
                                <i class="bi bi-device-hdd me-2"></i>
                                {{ __('admin.devices') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}">
                                <i class="bi bi-gear me-2"></i>
                                {{ __('admin.settings') }}
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
            
            <!-- Offcanvas sidebar for mobile -->
            <div class="offcanvas offcanvas-start px-2" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="sidebarMenuLabel">{{ __('common.menu') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('common.close') }}"></button>
                </div>
                <div class="offcanvas-body p-0">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.dashboard*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                                <i class="bi bi-bar-chart me-2"></i>
                                {{ __('admin.dashboard') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.content.*') ? 'active' : '' }}" href="{{ route('admin.content.index') }}">
                                <i class="bi bi-grid me-2"></i>
                                {{ __('admin.content') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.profile.*') ? 'active' : '' }}" href="{{ route('admin.profile.edit') }}">
                                <i class="bi bi-person me-2"></i>
                                {{ __('admin.my_profile') }}
                            </a>
                        </li>
                        @if(auth()->user()->children()->count() > 0 || auth()->user()->role === 'user')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.children.*') ? 'active' : '' }}" href="{{ route('admin.children.index') }}">
                                    <i class="bi bi-heart me-2"></i>
                                    {{ __('admin.my_children') }}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.parent-devices.*') ? 'active' : '' }}" href="{{ route('admin.parent-devices.index') }}">
                                    <i class="bi bi-display me-2"></i>
                                    {{ __('admin.my_devices') }}
                                </a>
                            </li>
                        @endif
                        @if(auth()->check() && auth()->user()->isAdmin())
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.users.*') && !request()->routeIs('admin.profile.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                                    <i class="bi bi-people me-2"></i>
                                    {{ __('admin.users') }}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.devices.*') && !request()->routeIs('admin.parent-devices.*') ? 'active' : '' }}" href="{{ route('admin.devices.index') }}">
                                    <i class="bi bi-device-hdd me-2"></i>
                                    {{ __('admin.devices') }}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}">
                                    <i class="bi bi-gear me-2"></i>
                                    {{ __('admin.settings') }}
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
            
            {{-- Routes and data for admin-layout.js --}}
            <meta name="logout-route" content="{{ route('logout') }}">
            <meta name="profile-selection-route" content="{{ route('profile-selection') }}">
            
            <div class="col-12 col-md-8 col-lg-9 p-2 p-md-4">
                @if(session('success'))
                    <x-ui.toast-notification type="success" message="{{ session('success') }}" />
                @endif
                
                @if(session('error'))
                    <x-ui.toast-notification type="error" message="{{ session('error') }}" />
                @endif
                
                @yield('content')
            </div>
        </div>
    </div>
    
    <!-- Toast Container for stacking notifications -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 1055;"></div>
    
    <!-- Toast Template for JavaScript cloning -->
    <x-ui.toast-notification-template />
    
    {{-- Options menu offcanvas --}}
    @auth
        <x-ui.options-menu-offcanvas 
            variant="light" 
            :show-register-account="false"
            :show-logout-device="true"
            :show-admin-button="false" 
        />
    @endauth
    
    
    @stack('scripts')
</body>
</html>

