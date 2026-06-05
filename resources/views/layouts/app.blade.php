<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    {{-- PWA Meta Tags --}}
    <meta name="theme-color" content="#212529">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Traktor">
    
    {{-- Author Information --}}
    <meta name="author" content="Jonan Steiner">
    <meta name="contact" content="jonan.steiner@gmail.com">
    
    {{-- Asset Version for Cache Invalidation --}}
    @php
        try {
            $assetVersion = \App\Models\Setting::where('key', 'asset_version')->value('value') ?? '0';
        } catch (\Exception $e) {
            $assetVersion = '0';
        }
    @endphp
    <meta name="asset-version" content="{{ $assetVersion ?? '0' }}">
    
    {{-- Favicons and Icons --}}
    {{-- Prefer SVG favicon (modern browsers), fallback to ICO for older browsers --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v={{ $assetVersion ?? '0' }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ $assetVersion ?? '0' }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ $assetVersion ?? '0' }}">
    
    {{-- Web App Manifest --}}
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    
    {{-- Preconnect to YouTube CDN for faster image loading --}}
    <link rel="preconnect" href="https://i.ytimg.com">
    <link rel="dns-prefetch" href="https://i.ytimg.com">
    
    {{-- Critical CSS to prevent FOUC - hide modals and offcanvas before main CSS loads --}}
    <style>
        /* Hide modals and offcanvas by default to prevent FOUC */
        .modal:not(.show) {
            display: none !important;
        }
        .offcanvas:not(.show) {
            visibility: hidden;
        }
        /* Hide modal close buttons when their modal is not shown (prevents FOUC) */
        #passwordLoginModal:not(.show) .password-login-modal-close,
        #pinEntryModal:not(.show) .pin-entry-modal-close,
        #adminPasswordModal:not(.show) .admin-password-modal-close,
        #pendingApprovalModal:not(.show) .pending-approval-modal-close {
            opacity: 0;
            pointer-events: none;
        }
        /* Ensure body has dark background immediately to prevent white flash */
        body {
            background-color: #212529;
            color: #f8f9fa;
        }
    </style>
    
    <title>@yield('title', 'Traktor')</title>
    {{-- Preload critical CSS for faster rendering (only in production with manifest) --}}
    @if(app()->environment('production'))
        @php
            $manifestPath = public_path('build/manifest.json');
            if (file_exists($manifestPath)) {
                try {
                    $viteManifest = json_decode(file_get_contents($manifestPath), true);
                    $cssFile = $viteManifest['resources/css/app.scss']['file'] ?? null;
                    if ($cssFile) {
                        $cssUrl = asset('build/' . $cssFile);
                    }
                } catch (\Exception $e) {
                    // Silently fail if manifest can't be read
                }
            }
        @endphp
        @if(isset($cssUrl))
            <link rel="preload" href="{{ $cssUrl }}" as="style">
        @endif
    @endif
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
    @stack('styles')
    @stack('scripts')
</head>
<body class="@yield('body-class', 'bg-dark text-light')">
    @yield('content')
    
    <!-- Toast Container for stacking notifications -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 1055;"></div>
    
    <!-- Toast Template for JavaScript cloning -->
    <x-ui.toast-notification-template />
    
    @if($hasRegisteredDevice ?? false)
        @push('scripts-bottom')
            @vite('resources/js/admin/shared/admin-password-modal.js')
        @endpush
    @endif
    
    @stack('scripts-bottom')
    
    {{-- Embed translations for JavaScript --}}
    @php
        $translations = [
            'common' => __('common'),
            'auth' => __('auth'),
            'messages' => __('messages'),
            'welcome' => __('welcome'),
            'admin' => __('admin'),
            'gallery' => __('gallery'),
            'forms' => __('forms'),
            'account' => __('account'),
        ];
    @endphp
    <script>
        window.appTranslations = @json($translations);
        window.appLocale = '{{ app()->getLocale() }}';
    </script>

    @if(Route::has('api.device.refresh-capabilities'))
        <script>
            window.Traktor = window.Traktor || {};
            window.Traktor.Device = window.Traktor.Device || {};
            window.Traktor.Device.capabilityRefresh = {
                needed: {{ ($deviceNeedsCapabilityRefresh ?? false) ? 'true' : 'false' }},
                route: "{{ route('api.device.refresh-capabilities') }}",
                csrf: "{{ csrf_token() }}",
                storageKey: "capability_refresh_{{ optional($device)->id ?? 'guest' }}",
                deviceId: {{ optional($device)->id ?? 'null' }}
            };
        </script>
    @endif
    
    <script>
        // Simple, reliable toast initialization system
        // Works for: same page (no refresh), same page (with refresh), and redirected pages
        
        (function() {
            let toastObserver = null;
            let initAttempts = 0;
            const maxInitAttempts = 50; // 5 seconds max (50 * 100ms)
            
            // Initialize a single toast element
            function initializeToast(toastEl) {
                if (!toastEl || toastEl.dataset.initialized === 'true') return false;
                
                const toastContainer = document.getElementById('toastContainer');
                if (!toastContainer) return false;
                
                // Check if Bootstrap is available
                if (typeof bootstrap === 'undefined' || !bootstrap.Toast) {
                    return false;
                }
                
                // Move toast to container if needed
                if (toastEl.parentElement !== toastContainer) {
                    toastContainer.appendChild(toastEl);
                }
                
                // Get settings
                const autohide = toastEl.getAttribute('data-bs-autohide') !== 'false';
                const delay = autohide ? 5000 : 0;
                
                // Initialize and show
                try {
                    const toast = new bootstrap.Toast(toastEl, {
                        autohide: autohide,
                        delay: delay
                    });
                    toast.show();
                    toastEl.dataset.initialized = 'true';
                    
                    // Clean up after hide
                    if (autohide) {
                        toastEl.addEventListener('hidden.bs.toast', function() {
                            toastEl.remove();
                        }, { once: true });
                    }
                    
                    return true;
                } catch (e) {
                    // Silently fail - toast initialization error is non-critical
                    // Error is logged but doesn't break functionality
                    return false;
                }
            }
            
            // Find and initialize all toasts on the page
            function initializeAllToasts() {
                initAttempts++;
                
                // Check prerequisites
                const toastContainer = document.getElementById('toastContainer');
                if (!toastContainer) {
                    if (initAttempts < maxInitAttempts) {
                        setTimeout(initializeAllToasts, 100);
                    }
                    return;
                }
                
                if (typeof bootstrap === 'undefined' || !bootstrap.Toast) {
                    if (initAttempts < maxInitAttempts) {
                        setTimeout(initializeAllToasts, 100);
                    }
                    return;
                }
                
                // Find all toast elements (anywhere in the DOM)
                const toastElements = document.querySelectorAll('.toast:not([data-initialized="true"])');
                let initialized = 0;
                
                toastElements.forEach(function(toastEl) {
                    if (initializeToast(toastEl)) {
                        initialized++;
                    }
                });
                
                // If we found toasts but Bootstrap wasn't ready, retry
                if (toastElements.length > 0 && initialized === 0 && initAttempts < maxInitAttempts) {
                    setTimeout(initializeAllToasts, 100);
                }
            }
            
            // Set up MutationObserver to catch dynamically added toasts
            function setupObserver() {
                if (toastObserver) return; // Already set up
                
                const toastContainer = document.getElementById('toastContainer');
                if (!toastContainer) return;
                
                toastObserver = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1 && node.classList && node.classList.contains('toast')) {
                                // Small delay to ensure element is fully in DOM
                                setTimeout(function() {
                                    initializeToast(node);
                                }, 10);
                            }
                        });
                    });
                });
                
                // Observe entire document for toast elements
                toastObserver.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
            
            // Main initialization function
            function init() {
                // Set up observer first
                setupObserver();
                
                // Initialize existing toasts
                initializeAllToasts();
            }
            
            // Start initialization
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
            
            // Also try on window load (for late-loading content)
            window.addEventListener('load', function() {
                initAttempts = 0; // Reset attempts for window load
                initializeAllToasts();
            });
        })();
    </script>
</body>
</html>

