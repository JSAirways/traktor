@extends('layouts.frontend')

@section('title', __('forms.register_device') . ' - ' . __('common.app_name'))

@section('main-content')
<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card bg-dark border-success text-light">
            <div class="card-body p-3">
                <x-ui.user-avatar 
                    image="{{ asset('assets/cats/Art_Working_Sticker_by_Pusheen.gif') }}"
                    :title="__('forms.register_device')"
                    variant="normal"
                />

                <x-ui.flash-messages />

                <form method="POST" action="{{ route('device.register') }}" id="deviceRegistrationForm" autocomplete="off">
                    @csrf
                    <input type="hidden" name="device_fingerprint" id="device_fingerprint">
                    <input type="hidden" name="user_agent" id="user_agent">
                    <input type="hidden" name="screen_resolution" id="screen_resolution">
                    <input type="hidden" name="capabilities" id="device_capabilities">
                    
                    <x-forms.form-field 
                        name="device_name" 
                        :label="__('common.device_name')" 
                        :required="true"
                        :value="old('device_name')"
                        :autofocus="!$errors->has('password') && !$errors->has('email')"
                    />

                    <x-forms.form-field 
                        name="email" 
                        :label="__('common.email')" 
                        type="email"
                        :required="true"
                        value=""
                    />

                    <x-forms.form-field 
                        name="password" 
                        :label="__('common.password')" 
                        type="password"
                        :required="true"
                    />

                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-success w-100">{{ __('forms.register_device') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const fingerprintUtils = (window.Traktor && window.Traktor.Core && window.Traktor.Core.deviceFingerprint)
        ? window.Traktor.Core.deviceFingerprint
        : null;

    // Collect browser characteristics
    function collectBrowserData() {
        if (fingerprintUtils && typeof fingerprintUtils.collectBrowserData === 'function') {
            return fingerprintUtils.collectBrowserData();
        }

        return {
            user_agent: navigator.userAgent || '',
            screen_width: screen.width || 0,
            screen_height: screen.height || 0,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
            language: navigator.language || navigator.userLanguage || '',
            platform: navigator.platform || '',
            color_depth: screen.colorDepth || 0,
            pixel_ratio: window.devicePixelRatio || 1,
        };
    }

    // Generate device fingerprint via PHP/AJAX API (fallback for browsers without crypto.subtle)
    // Uses XMLHttpRequest utility for universal browser compatibility
    function generateFingerprintViaAPI(browserData, apiRoute, csrfToken) {
        // Use makeRequest utility if available (from utils.js), otherwise inline version
        if (typeof window.makeRequest === 'function') {
            return window.makeRequest(apiRoute, {
                method: 'POST',
                body: browserData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                responseType: 'json'
            }).then(data => data.fingerprint);
        }
        
        // Fallback inline implementation for this template
        return new Promise(function(resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', apiRoute, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 10000;
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        resolve(data.fingerprint);
                    } catch (e) {
                        reject(new Error('Invalid JSON response'));
                    }
                } else {
                    reject(new Error('Network response was not ok: ' + xhr.status));
                }
            };
            
            xhr.onerror = function() {
                reject(new Error('Network request failed'));
            };
            
            xhr.ontimeout = function() {
                reject(new Error('Request timeout'));
            };
            
            try {
                xhr.send(JSON.stringify(browserData));
            } catch (e) {
                reject(new Error('Failed to send request: ' + e.message));
            }
        });
    }

    // Generate device fingerprint (must match PHP DeviceRegistration::generateFingerprint)
    async function generateDeviceFingerprint(browserData, apiRoute = null, csrfToken = null) {
        if (fingerprintUtils && typeof fingerprintUtils.generateDeviceFingerprint === 'function') {
            return fingerprintUtils.generateDeviceFingerprint(browserData, apiRoute, csrfToken);
        }

        // Use pipe-separated format to match PHP implementation
        const fingerprintString = [
            browserData.user_agent || '',
            browserData.screen_width || '',
            browserData.screen_height || '',
            browserData.timezone || '',
            browserData.language || '',
            browserData.platform || '',
            browserData.color_depth || '',
            browserData.pixel_ratio || '',
        ].join('|');
        
        // Check if crypto.subtle is available (modern browsers)
        if (typeof crypto !== 'undefined' && crypto.subtle && crypto.subtle.digest) {
            try {
                const encoder = new TextEncoder();
                const data = encoder.encode(fingerprintString);
                const hashBuffer = await crypto.subtle.digest('SHA-256', data);
                const hashArray = Array.from(new Uint8Array(hashBuffer));
                return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
            } catch (error) {
                // Fallback to PHP/AJAX API if crypto.subtle fails
                if (apiRoute && csrfToken) {
                    return generateFingerprintViaAPI(browserData, apiRoute, csrfToken);
                }
                throw new Error('crypto.subtle failed and no API fallback available');
            }
        } else {
            // No crypto.subtle - use PHP/AJAX API directly (iOS 10, PS4 browser)
            if (apiRoute && csrfToken) {
                return generateFingerprintViaAPI(browserData, apiRoute, csrfToken);
            }
            throw new Error('crypto.subtle not available and no API fallback provided');
        }
    }

    // Set fingerprint and browser data in form
    async function setFingerprintInForms() {
        const browserData = collectBrowserData();
        if (!browserData) {
            return;
        }
        
        const apiRoute = '{{ route("api.device.generate-fingerprint") }}';
        const csrfToken = '{{ csrf_token() }}';
        const urlParams = new URLSearchParams(window.location.search);
        let fingerprint = urlParams.get('device_fingerprint');
        const capabilities = (fingerprintUtils && typeof fingerprintUtils.collectCapabilities === 'function')
            ? fingerprintUtils.collectCapabilities(browserData)
            : null;
        
        if (!fingerprint) {
            fingerprint = await generateDeviceFingerprint(browserData, apiRoute, csrfToken);
        }
        
        if (fingerprintUtils && typeof fingerprintUtils.setFingerprintInForms === 'function') {
            fingerprintUtils.setFingerprintInForms(fingerprint, browserData, capabilities);
            return;
        }
        
        const resolution = `${browserData.screen_width || screen.width || 0}x${browserData.screen_height || screen.height || 0}`;
        
        setHiddenValue('device_fingerprint', fingerprint);
        setHiddenValue('user_agent', browserData.user_agent || navigator.userAgent || '');
        setHiddenValue('screen_resolution', resolution);
        setHiddenValue('passwordFormFingerprint', fingerprint);
        setHiddenValue('passwordFormUserAgent', browserData.user_agent || navigator.userAgent || '');
        setHiddenValue('passwordFormScreenResolution', resolution);
        setHiddenValue('passwordLoginModalFingerprint', fingerprint);
        setHiddenValue('passwordLoginModalUserAgent', browserData.user_agent || navigator.userAgent || '');
        setHiddenValue('passwordLoginModalScreenResolution', resolution);
        
        const capabilityJson = serializeCapabilities(capabilities);
        setHiddenValue('device_capabilities', capabilityJson);
        setHiddenValue('passwordFormCapabilities', capabilityJson);
        setHiddenValue('passwordLoginModalCapabilities', capabilityJson);
    }

    function serializeCapabilities(data) {
        if (!data || typeof data !== 'object') {
            return '';
        }
        try {
            return JSON.stringify(data);
        } catch (error) {
            return '';
        }
    }

    function setHiddenValue(elementId, value) {
        const element = document.getElementById(elementId);
        if (!element) {
            return;
        }
        element.value = (typeof value === 'undefined' || value === null) ? '' : value;
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Set fingerprint on page load
        setFingerprintInForms();
        
        // Only clear username and password fields if there are no validation errors
        // If there are errors, keep the old input values so user can see what they typed
        @if(!$errors->has('password') && !$errors->has('username'))
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            if (usernameField) {
                usernameField.value = '';
            }
            if (passwordField) {
                passwordField.value = '';
            }
        @else
            // If there are validation errors, focus username field and ensure error styling
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            setTimeout(() => {
                if (usernameField) {
                    usernameField.focus();
                    usernameField.classList.add('is-invalid');
                }
                if (passwordField) {
                    passwordField.classList.add('is-invalid');
                }
            }, 100);
        @endif
        
        // Handle form submission - ensure fingerprint is set before allowing submission
        const registrationForm = document.getElementById('deviceRegistrationForm');
        if (registrationForm) {
            registrationForm.addEventListener('submit', async function(e) {
                // Ensure fingerprint is set before submission
                await setFingerprintInForms();
                // Let form submit normally - server handles everything
            });
        }
    });
</script>
@endpush
@endsection

