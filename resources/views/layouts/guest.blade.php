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
        <link rel="author" href="https://jonan.space">
        
        {{-- Favicons and Icons --}}
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
        
        {{-- Web App Manifest --}}
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">
        
        {{-- Asset Version for Cache Invalidation --}}
        @php
            $assetVersion = \App\Models\Setting::where('key', 'asset_version')->value('value') ?? '0';
        @endphp
        <meta name="asset-version" content="{{ $assetVersion }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.scss', 'resources/js/app.js'])
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </head>
    <body class="bg-dark">
        <div class="min-vh-100 d-flex flex-column justify-content-center align-items-center bg-dark py-5">
            <div class="mb-4">
                <a href="/">
                    <img src="{{ asset('tractor.png') }}" width="80" height="80" alt="{{ __('common.app_name') }}" />
                </a>
            </div>

            <div class="w-100" style="max-width: 28rem;">
                <div class="card shadow">
                    <div class="card-body bg-dark text-light p-4">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Toast Container for stacking notifications -->
        <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 1055;"></div>
        
        <!-- Toast Template for JavaScript cloning -->
        <x-ui.toast-notification-template />
        
        <script>
            // Initialize and show toasts
            document.addEventListener('DOMContentLoaded', function() {
                const toastContainer = document.getElementById('toastContainer');
                if (toastContainer && typeof bootstrap !== 'undefined' && bootstrap.Toast) {
                    // Find all toast elements on the page
                    const toastElements = document.querySelectorAll('.toast');
                    
                    toastElements.forEach(function(toastEl) {
                        // Move toast to container if not already there
                        if (toastEl.parentElement !== toastContainer) {
                            toastContainer.appendChild(toastEl);
                        }
                        
                        // Check if toast should auto-hide (respect data-bs-autohide attribute)
                        const autohide = toastEl.getAttribute('data-bs-autohide') !== 'false';
                        const delay = autohide ? 5000 : 0;
                        
                        // Initialize and show the toast
                        const toast = new bootstrap.Toast(toastEl, {
                            autohide: autohide,
                            delay: delay
                        });
                        toast.show();
                        
                        // Remove toast element after it's hidden (only for auto-hiding toasts)
                        if (autohide) {
                            toastEl.addEventListener('hidden.bs.toast', function() {
                                toastEl.remove();
                            }, { once: true });
                        }
                    });
                }
            });
        </script>
    </body>
</html>
