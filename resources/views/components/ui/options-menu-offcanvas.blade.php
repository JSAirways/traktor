@props([
    'variant' => 'dark', // 'dark' | 'light'
    'showAdminButton' => true, // Show admin password modal button
    'showRegisterAccount' => false, // Show register account button
    'showLogoutDevice' => true, // Show logout device button
])

@php
    $isDark = $variant === 'dark';
    $bgClass = $isDark ? 'bg-dark' : 'bg-light';
    $textClass = $isDark ? 'text-light' : 'text-dark';
    $btnClass = $isDark ? 'btn-outline-light' : 'btn-outline-success';
    $borderClass = $isDark ? 'border-secondary' : 'border-light';
@endphp

{{--
    Options Menu Offcanvas Component
    
    A collapsible offcanvas menu that slides in from the right.
    Contains logout device, admin password modal button, register account button, and language toggle.
    Works on all viewports (not just mobile like admin sidebar).
    Note: Profile selection button has been moved to the navbar for better accessibility.
    
    @prop string $variant - Theme variant: 'dark' (default) or 'light' (for admin area)
    @prop bool $showAdminButton - Whether to show admin password modal button (default: true)
    @prop bool $showRegisterAccount - Whether to show register account button (default: false)
    @prop bool $showLogoutDevice - Whether to show logout device button (default: true)
    
    @example
    <x-ui.options-menu-offcanvas variant="dark" :show-admin-button="true" />
--}}

<div class="offcanvas offcanvas-end px-2 {{ $bgClass }} {{ $textClass }} options-menu-offcanvas" tabindex="-1" id="optionsMenuOffcanvas" aria-labelledby="optionsMenuOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="optionsMenuOffcanvasLabel">{{ __('gallery.options') }}</h5>
        <button type="button" class="btn {{ $isDark ? 'btn-outline-light' : 'btn-outline-dark' }} border-0 ms-auto" data-bs-dismiss="offcanvas" aria-label="Close">
            <i class="bi bi-x-lg fs-4"></i>
        </button>
    </div>
    <div class="offcanvas-body p-0">
        <ul class="nav flex-column">
            {{-- 1. Register Account Button (where applicable) --}}
            @if($showRegisterAccount)
                <li class="nav-item">
                    <a href="{{ route('register-account') }}" 
                       class="nav-link border-top-0 border-start-0 border-end-0 bg-transparent text-start w-100" 
                       title="{{ __('forms.register_account') }}">
                        <i class="bi bi-person-plus me-2"></i>
                        {{ __('forms.register_account') }}
                    </a>
                </li>
            @endif

            {{-- 3. Logout Device Button (conditional) --}}
            @if($showLogoutDevice)
            <li class="nav-item">
                <form method="POST" action="{{ route('device.logout') }}" class="d-inline w-100 mb-0">
                    @csrf
                    <button type="submit" 
                            class="nav-link border-top-0 border-start-0 border-end-0 bg-transparent text-start w-100" 
                            onclick="return confirm('{{ __('common.are_you_sure') }}')"
                            data-bs-dismiss="offcanvas">
                        <i class="bi bi-box-arrow-right me-2"></i>
                        {{ __('admin.logout_device') }}
                    </button>
                </form>
            </li>
            @endif

            {{-- 4. Admin Password Modal Button --}}
            @if($showAdminButton)
                <li class="nav-item">
                    <button type="button" 
                            class="nav-link border-top-0 border-start-0 border-end-0 bg-transparent text-start w-100" 
                            data-bs-toggle="modal" 
                            data-bs-target="#adminPasswordModal"
                            title="{{ __('gallery.settings') }}">
                        <i class="bi bi-sliders me-2"></i>
                        {{ __('auth.admin_access') }}
                    </button>
                </li>
            @endif

            {{-- 5. Language Toggle (Dropdown) --}}
            @php
                $supportedLocales = config('app.supported_locales', ['en']);
                $currentLocale = app()->getLocale();
                $shouldShow = count($supportedLocales) > 1;
                
                $localeFlags = [
                    'en' => 'gb',
                    'de' => 'de',
                ];
                
                $localeNames = [
                    'en' => 'English',
                    'de' => 'Deutsch',
                ];
            @endphp

            @if($shouldShow)
                <li class="nav-item">
                    <div class="dropdown">
                        <button 
                            class="nav-link dropdown-toggle border-top-0 border-start-0 border-end-0 bg-transparent text-start w-100" 
                            type="button" 
                            id="optionsLocaleSwitcherBtn"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            aria-haspopup="true"
                            title="{{ __('common.change_language') }}">
                            <i class="bi bi-translate me-2"></i>
                            {{ __('common.change_language') }}
                        </button>
                        <ul class="dropdown-menu w-100" aria-labelledby="optionsLocaleSwitcherBtn">
                            @foreach($supportedLocales as $locale)
                                <li>
                                    <form method="POST" action="{{ route('locale.switch') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="locale" value="{{ $locale }}">
                                        <input type="hidden" name="redirect" value="{{ url()->current() }}">
                                        <button 
                                            type="submit" 
                                            class="dropdown-item d-flex align-items-center {{ $currentLocale === $locale ? 'active' : '' }}"
                                            data-bs-dismiss="offcanvas">
                                            <img src="{{ asset('assets/flags/' . ($localeFlags[$locale] ?? 'gb') . '.png') }}" 
                                                 alt="{{ $localeNames[$locale] ?? strtoupper($locale) }}" 
                                                 class="me-2 options-menu-flag-icon">
                                            <span>{{ $localeNames[$locale] ?? strtoupper($locale) }}</span>
                                            @if($currentLocale === $locale)
                                                <i class="bi bi-check ms-auto"></i>
                                            @endif
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </li>
            @endif
        </ul>
    </div>
</div>

@push('scripts')
    @vite('resources/js/resources/shared/options-menu-offcanvas.js')
@endpush

