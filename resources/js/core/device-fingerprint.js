/**
 * Device Fingerprint Utilities
 * Shared functions for device fingerprinting and browser data collection
 */

import { makeRequest } from './utils.js';

/**
 * Generates device fingerprint via PHP/AJAX API (fallback for browsers without crypto.subtle)
 * Uses XMLHttpRequest for universal browser compatibility
 * @param {object} browserData - Browser data object from collectBrowserData()
 * @param {string} apiRoute - API route for fingerprint generation
 * @param {string} csrfToken - CSRF token for the request
 * @returns {Promise<string>} Promise that resolves to hexadecimal fingerprint string
 */
export function generateFingerprintViaAPI(browserData, apiRoute, csrfToken) {
    if (!apiRoute || !csrfToken) {
        return Promise.reject(new Error('Missing API route or CSRF token for fingerprint generation'));
    }
    
    return makeRequest(apiRoute, {
        method: 'POST',
        body: browserData,
        headers: {
            'X-CSRF-TOKEN': csrfToken
        },
        responseType: 'json'
    })
    .then(response => {
        // Extract data from response object if needed
        let data = response;
        if (response && typeof response === 'object' && 'data' in response) {
            data = response.data;
        }
        
        // Extract fingerprint from response
        if (data && data.fingerprint) {
            return data.fingerprint;
        }
        
        // If no fingerprint in expected location, try direct access
        if (typeof response === 'object' && response.fingerprint) {
            return response.fingerprint;
        }
        
        throw new Error('Fingerprint not found in API response');
    })
    .catch(error => {
        throw error;
    });
}

/**
 * Collects browser characteristics for device fingerprinting
 * @returns {object} Browser data object with user agent, screen, timezone, etc.
 */
export function collectBrowserData() {
    let timezone = '';
    let timezoneOffset = null;

    try {
        if (typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
            const resolved = Intl.DateTimeFormat().resolvedOptions();
            timezone = resolved.timeZone || '';
        }
    } catch (e) {
        timezone = '';
    }

    try {
        const offset = -new Date().getTimezoneOffset();
        timezoneOffset = offset;
        if (!timezone) {
            const hours = Math.floor(Math.abs(offset) / 60);
            const minutes = Math.abs(offset) % 60;
            const sign = offset >= 0 ? '+' : '-';
            timezone = `UTC${sign}${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
        }
    } catch (e2) {
        timezoneOffset = null;
    }

    timezone = (timezone || '').trim();

    const scr = typeof screen !== 'undefined' ? screen : { width: 0, height: 0, colorDepth: 0 };
    const screenWidth = Math.floor(scr.width || 0);
    const screenHeight = Math.floor(scr.height || 0);
    const colorDepth = Math.floor(scr.colorDepth || 0);
    const pixelRatio = typeof window !== 'undefined' && window.devicePixelRatio
        ? Math.round(window.devicePixelRatio * 100) / 100
        : 1;
    let screenOrientation = '';

    try {
        if (typeof window !== 'undefined') {
            if (window.screen && window.screen.orientation && window.screen.orientation.type) {
                screenOrientation = window.screen.orientation.type;
            } else if (typeof window.orientation !== 'undefined') {
                screenOrientation = window.orientation === 0 ? 'portrait-primary' : 'landscape-primary';
            }
        }
    } catch (e3) {
        screenOrientation = '';
    }

    return {
        user_agent: (typeof navigator !== 'undefined' && (navigator.userAgent || navigator.appVersion))
            ? (navigator.userAgent || navigator.appVersion)
            : '',
        screen_width: screenWidth,
        screen_height: screenHeight,
        timezone: timezone,
        timezone_offset: timezoneOffset,
        language: (typeof navigator !== 'undefined' && (navigator.language || navigator.userLanguage))
            ? (navigator.language || navigator.userLanguage).toLowerCase()
            : '',
        platform: (typeof navigator !== 'undefined' && navigator.platform) ? navigator.platform : '',
        color_depth: colorDepth,
        pixel_ratio: pixelRatio,
        screen_orientation: screenOrientation,
    };
}

export function collectCapabilities(browserData) {
    const nav = typeof navigator !== 'undefined' ? navigator : {};
    const scr = typeof screen !== 'undefined' ? screen : {};

    const capabilities = {
        touch_support: !!(('ontouchstart' in (typeof window !== 'undefined' ? window : {})) || nav.maxTouchPoints > 0 || nav.msMaxTouchPoints > 0),
        max_touch_points: typeof nav.maxTouchPoints === 'number' ? nav.maxTouchPoints : null,
        hardware_concurrency: typeof nav.hardwareConcurrency === 'number' ? nav.hardwareConcurrency : null,
        device_memory: typeof nav.deviceMemory === 'number' ? nav.deviceMemory : null,
        prefers_reduced_motion: matchMediaPreference('(prefers-reduced-motion: reduce)'),
        prefers_dark_mode: matchMediaPreference('(prefers-color-scheme: dark)'),
        prefers_high_contrast: matchMediaPreference('(prefers-contrast: more)'),
        has_service_worker: !!nav.serviceWorker,
        has_indexed_db: typeof (typeof window !== 'undefined' ? window.indexedDB : undefined) !== 'undefined',
        has_local_storage: storageAvailable('localStorage'),
        has_session_storage: storageAvailable('sessionStorage'),
        has_webgl: supportsWebGL(),
        has_autoplay_inline: supportsInlineAutoplay(),
        pointer_accuracy: detectPointerAccuracy(),
        connection_type: nav.connection && nav.connection.effectiveType ? nav.connection.effectiveType : null,
        screen_orientation: browserData && browserData.screen_orientation
            ? browserData.screen_orientation
            : (scr.orientation && scr.orientation.type ? scr.orientation.type : null),
        timezone_offset: browserData && typeof browserData.timezone_offset !== 'undefined'
            ? browserData.timezone_offset
            : (() => {
                try {
                    return -new Date().getTimezoneOffset();
                } catch (e) {
                    return null;
                }
            })(),
        language: browserData && browserData.language ? browserData.language : (nav.language || nav.userLanguage || ''),
        platform: browserData && browserData.platform ? browserData.platform : (nav.platform || ''),
        screen_width: browserData && browserData.screen_width ? browserData.screen_width : (scr.width || null),
        screen_height: browserData && browserData.screen_height ? browserData.screen_height : (scr.height || null),
        pixel_ratio: browserData && browserData.pixel_ratio
            ? browserData.pixel_ratio
            : (typeof window !== 'undefined' && window.devicePixelRatio
                ? Math.round(window.devicePixelRatio * 100) / 100
                : null),
    };

    for (const key in capabilities) {
        if (Object.prototype.hasOwnProperty.call(capabilities, key) && typeof capabilities[key] === 'undefined') {
            delete capabilities[key];
        }
    }

    return capabilities;
}

function matchMediaPreference(query) {
    try {
        if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
            return window.matchMedia(query).matches;
        }
    } catch (e) {
        return null;
    }
    return null;
}

function storageAvailable(type) {
    try {
        const storage = typeof window !== 'undefined' ? window[type] : null;
        if (!storage) {
            return false;
        }
        const testKey = '__trk_capability_test__';
        storage.setItem(testKey, testKey);
        storage.removeItem(testKey);
        return true;
    } catch (e) {
        return false;
    }
}

function supportsWebGL() {
    try {
        if (typeof document === 'undefined') {
            return false;
        }
        const canvas = document.createElement('canvas');
        return !!(canvas.getContext && (canvas.getContext('webgl') || canvas.getContext('experimental-webgl')));
    } catch (e) {
        return false;
    }
}

function supportsInlineAutoplay() {
    try {
        if (typeof document === 'undefined') {
            return false;
        }
        const video = document.createElement('video');
        return typeof video.playsInline !== 'undefined';
    } catch (e) {
        return false;
    }
}

function detectPointerAccuracy() {
    try {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            return null;
        }
        if (window.matchMedia('(pointer: fine)').matches) return 'fine';
        if (window.matchMedia('(pointer: coarse)').matches) return 'coarse';
        if (window.matchMedia('(pointer: none)').matches) return 'none';
    } catch (e) {
        return null;
    }
    return null;
}

export function serializeCapabilities(capabilities) {
    if (!capabilities || typeof capabilities !== 'object') {
        return '';
    }

    try {
        return JSON.stringify(capabilities);
    } catch (e) {
        return '';
    }
}

/**
 * Generates a device fingerprint from browser data using SHA-256
 * Flow: First tries modern approach (crypto.subtle), then falls back to PHP/AJAX API for older devices
 * @param {object} browserData - Browser data object from collectBrowserData()
 * @param {string} [apiRoute] - Optional API route for fingerprint generation (fallback)
 * @param {string} [csrfToken] - Optional CSRF token for API request (fallback)
 * @returns {Promise<string>} Promise that resolves to hexadecimal fingerprint string
 */
export function generateDeviceFingerprint(browserData, apiRoute = null, csrfToken = null) {
    const fingerprintString = [
        browserData.user_agent,
        browserData.screen_width,
        browserData.screen_height,
        browserData.timezone,
        browserData.language,
        browserData.platform,
        browserData.color_depth,
        browserData.pixel_ratio,
    ].join('|');
    
    // STEP 1: Try modern approach first - crypto.subtle (client-side, no network)
    if (typeof crypto !== 'undefined' && crypto.subtle && crypto.subtle.digest) {
        try {
            // TextEncoder is natively supported on PS4+
            const textEncoder = new TextEncoder();
            return crypto.subtle.digest('SHA-256', textEncoder.encode(fingerprintString))
                .then(hashBuffer => {
                    const hashArray = Array.from(new Uint8Array(hashBuffer));
                    const hashHex = hashArray.map(b => {
                        return b.toString(16).padStart(2, '0');
                    }).join('');
                    return hashHex;
                })
                .catch(error => {
                    // STEP 2: Fallback to PHP/AJAX API if crypto.subtle fails at runtime
                    if (apiRoute && csrfToken) {
                        return generateFingerprintViaAPI(browserData, apiRoute, csrfToken);
                    }
                    throw new Error('crypto.subtle failed and no API fallback available');
                });
        } catch (e) {
            // STEP 2: Fallback to PHP/AJAX API if crypto.subtle throws synchronously
            if (apiRoute && csrfToken) {
                return generateFingerprintViaAPI(browserData, apiRoute, csrfToken);
            }
            return Promise.reject(new Error('crypto.subtle not available and no API fallback available'));
        }
    } else {
        // STEP 2: Fallback to PHP/AJAX API if crypto.subtle is not available
        if (apiRoute && csrfToken) {
            return generateFingerprintViaAPI(browserData, apiRoute, csrfToken);
        }
        return Promise.reject(new Error('crypto.subtle not available and no API fallback provided'));
    }
}

/**
 * Sets device fingerprint and browser data in all forms on the page
 * @param {string} fingerprint - The device fingerprint hash
 * @param {object} browserData - Browser data object from collectBrowserData()
 * @param {object} capabilities - Capability object from collectCapabilities()
 * @returns {void}
 */
export function setFingerprintInForms(fingerprint, browserData, capabilities) {
    const capabilityJson = serializeCapabilities(capabilities);
    const resolution = `${browserData.screen_width}x${browserData.screen_height}`;

    setInputValue('device_fingerprint', fingerprint);
    setInputValue('user_agent', browserData.user_agent);
    setInputValue('screen_resolution', resolution);
    setInputValue('device_capabilities', capabilityJson);

    setInputValue('passwordLoginModalFingerprint', fingerprint);
    setInputValue('passwordLoginModalUserAgent', browserData.user_agent);
    setInputValue('passwordLoginModalScreenResolution', resolution);
    setInputValue('passwordLoginModalCapabilities', capabilityJson);

    setInputValue('passwordFormFingerprint', fingerprint);
    setInputValue('passwordFormUserAgent', browserData.user_agent);
    setInputValue('passwordFormScreenResolution', resolution);
    setInputValue('passwordFormCapabilities', capabilityJson);
}

function setInputValue(elementId, value) {
    const element = document.getElementById(elementId);
    if (!element) {
        return;
    }

    element.value = typeof value === 'undefined' || value === null ? '' : value;
}
