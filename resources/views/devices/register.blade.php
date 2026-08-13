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
                    <input type="hidden" name="device_uid" id="device_uid">
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
    const deviceUtils = (window.Traktor && window.Traktor.Core && window.Traktor.Core.deviceIdentity)
        ? window.Traktor.Core.deviceIdentity
        : null;

    function collectBrowserData() {
        if (deviceUtils && typeof deviceUtils.collectBrowserData === 'function') {
            return deviceUtils.collectBrowserData();
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

    function generateUuidV4() {
        if (deviceUtils && typeof deviceUtils.generateUuidV4 === 'function') {
            return deviceUtils.generateUuidV4();
        }
        if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
            const bytes = new Uint8Array(16);
            crypto.getRandomValues(bytes);
            bytes[6] = (bytes[6] & 0x0f) | 0x40;
            bytes[8] = (bytes[8] & 0x3f) | 0x80;
            const hex = Array.from(bytes, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
            return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    function getOrCreateDeviceUid() {
        if (deviceUtils && typeof deviceUtils.getOrCreateDeviceUid === 'function') {
            return deviceUtils.getOrCreateDeviceUid();
        }

        var STORAGE_KEY = 'traktor_device_uid';
        var uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

        function readStore(store) {
            try {
                if (!store) return null;
                var existing = store.getItem(STORAGE_KEY);
                if (existing && uuidRegex.test(existing)) {
                    return existing.toLowerCase();
                }
            } catch (e) {}
            return null;
        }

        function writeStore(store, uid) {
            try {
                if (store) store.setItem(STORAGE_KEY, uid);
            } catch (e) {}
        }

        var fromLocal = readStore(typeof localStorage !== 'undefined' ? localStorage : null);
        if (fromLocal) {
            writeStore(typeof sessionStorage !== 'undefined' ? sessionStorage : null, fromLocal);
            return fromLocal;
        }

        var fromSession = readStore(typeof sessionStorage !== 'undefined' ? sessionStorage : null);
        if (fromSession) {
            writeStore(typeof localStorage !== 'undefined' ? localStorage : null, fromSession);
            return fromSession;
        }

        // Do not read device_uid from the URL (fixation risk).
        var created = generateUuidV4().toLowerCase();
        writeStore(typeof localStorage !== 'undefined' ? localStorage : null, created);
        writeStore(typeof sessionStorage !== 'undefined' ? sessionStorage : null, created);
        return created;
    }

    function setDeviceFieldsInForms() {
        var browserData = collectBrowserData();
        if (!browserData) {
            return;
        }

        var deviceUid = getOrCreateDeviceUid();
        var capabilities = (deviceUtils && typeof deviceUtils.collectCapabilities === 'function')
            ? deviceUtils.collectCapabilities(browserData)
            : null;

        if (deviceUtils && typeof deviceUtils.setDeviceUidInForms === 'function') {
            deviceUtils.setDeviceUidInForms(deviceUid, browserData, capabilities);
            return;
        }

        var resolution = (browserData.screen_width || screen.width || 0) + 'x' + (browserData.screen_height || screen.height || 0);
        var capabilityJson = '';
        try {
            capabilityJson = capabilities ? JSON.stringify(capabilities) : '';
        } catch (e) {
            capabilityJson = '';
        }

        setHiddenValue('device_uid', deviceUid);
        setHiddenValue('user_agent', browserData.user_agent || navigator.userAgent || '');
        setHiddenValue('screen_resolution', resolution);
        setHiddenValue('device_capabilities', capabilityJson);
    }

    function setHiddenValue(elementId, value) {
        var element = document.getElementById(elementId);
        if (!element) {
            return;
        }
        element.value = (typeof value === 'undefined' || value === null) ? '' : value;
    }

    document.addEventListener('DOMContentLoaded', function() {
        setDeviceFieldsInForms();

        @if(!$errors->has('password') && !$errors->has('username'))
            var usernameField = document.getElementById('username');
            var passwordField = document.getElementById('password');
            if (usernameField) {
                usernameField.value = '';
            }
            if (passwordField) {
                passwordField.value = '';
            }
        @else
            var usernameFieldErr = document.getElementById('username');
            var passwordFieldErr = document.getElementById('password');
            setTimeout(function () {
                if (usernameFieldErr) {
                    usernameFieldErr.focus();
                    usernameFieldErr.classList.add('is-invalid');
                }
                if (passwordFieldErr) {
                    passwordFieldErr.classList.add('is-invalid');
                }
            }, 100);
        @endif

        var registrationForm = document.getElementById('deviceRegistrationForm');
        if (registrationForm) {
            registrationForm.addEventListener('submit', function () {
                setDeviceFieldsInForms();
            });
        }
    });
</script>
@endpush
@endsection
