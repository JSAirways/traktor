/**
 * Device identity utilities
 * Durable device_uid, browser metadata, and capabilities
 */

const DEVICE_UID_STORAGE_KEY = 'traktor_device_uid';
const UUID_V4_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

function exposeDeviceIdentityGlobals() {
    if (typeof window === 'undefined') {
        return;
    }
    window.Traktor = window.Traktor || {};
    window.Traktor.Core = window.Traktor.Core || {};
    window.Traktor.Core.deviceIdentity = {
        generateUuidV4,
        isValidDeviceUid,
        persistDeviceUid,
        getOrCreateDeviceUid,
        collectBrowserData,
        collectCapabilities,
        serializeCapabilities,
        setDeviceUidInForms,
    };
}

/**
 * Generate a UUID v4 without crypto.randomUUID (PS4 / older WebKit safe).
 * @returns {string}
 */
export function generateUuidV4() {
    if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
        return (
            hex.slice(0, 8) + '-' +
            hex.slice(8, 12) + '-' +
            hex.slice(12, 16) + '-' +
            hex.slice(16, 20) + '-' +
            hex.slice(20)
        );
    }

    // Math.random fallback for environments without getRandomValues
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

export function isValidDeviceUid(uid) {
    return typeof uid === 'string' && UUID_V4_REGEX.test(uid.trim());
}

function readLocalStorageUid() {
    try {
        if (typeof localStorage === 'undefined') {
            return null;
        }
        const value = localStorage.getItem(DEVICE_UID_STORAGE_KEY);
        return isValidDeviceUid(value) ? value.trim().toLowerCase() : null;
    } catch (e) {
        return null;
    }
}

function writeLocalStorageUid(uid) {
    if (!isValidDeviceUid(uid)) {
        return false;
    }
    try {
        if (typeof localStorage === 'undefined') {
            return false;
        }
        localStorage.setItem(DEVICE_UID_STORAGE_KEY, uid.trim().toLowerCase());
        return true;
    } catch (e) {
        return false;
    }
}

/**
 * Persist a server-confirmed device_uid to localStorage and sessionStorage when available.
 * @param {string} uid
 * @returns {string|null}
 */
export function persistDeviceUid(uid) {
    if (!isValidDeviceUid(uid)) {
        return null;
    }
    const normalized = uid.trim().toLowerCase();
    writeLocalStorageUid(normalized);
    writeSessionStorageUid(normalized);
    memoryDeviceUid = normalized;
    return normalized;
}

/**
 * Get or create a durable device_uid.
 * Order: localStorage → sessionStorage → in-memory (same JS realm) → hidden form → mint.
 * Never reads device_uid from the URL (avoids fixation).
 * @returns {string}
 */
export function getOrCreateDeviceUid() {
    const fromLocal = readLocalStorageUid();
    if (fromLocal) {
        writeSessionStorageUid(fromLocal);
        memoryDeviceUid = fromLocal;
        return fromLocal;
    }

    const fromSession = readSessionStorageUid();
    if (fromSession) {
        writeLocalStorageUid(fromSession);
        memoryDeviceUid = fromSession;
        return fromSession;
    }

    if (memoryDeviceUid && isValidDeviceUid(memoryDeviceUid)) {
        writeLocalStorageUid(memoryDeviceUid);
        writeSessionStorageUid(memoryDeviceUid);
        return memoryDeviceUid;
    }

    // Prefer a hidden form field already set on the page (same navigation)
    try {
        const field = document.getElementById('device_uid')
            || document.getElementById('passwordLoginModalDeviceUid')
            || document.getElementById('passwordFormDeviceUid');
        if (field && isValidDeviceUid(field.value)) {
            return persistDeviceUid(field.value) || field.value.trim().toLowerCase();
        }
    } catch (e) {
        // ignore DOM access errors
    }

    const created = generateUuidV4().toLowerCase();
    return persistDeviceUid(created) || created;
}

function readSessionStorageUid() {
    try {
        if (typeof sessionStorage === 'undefined') {
            return null;
        }
        const value = sessionStorage.getItem(DEVICE_UID_STORAGE_KEY);
        return isValidDeviceUid(value) ? value.trim().toLowerCase() : null;
    } catch (e) {
        return null;
    }
}

function writeSessionStorageUid(uid) {
    if (!isValidDeviceUid(uid)) {
        return false;
    }
    try {
        if (typeof sessionStorage === 'undefined') {
            return false;
        }
        sessionStorage.setItem(DEVICE_UID_STORAGE_KEY, uid.trim().toLowerCase());
        return true;
    } catch (e) {
        return false;
    }
}

/** Same-tab / same-realm fallback when both storages fail (e.g. some PS4 modes). */
let memoryDeviceUid = null;

/**
 * Collects browser characteristics for admin display / capabilities
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
 * Sets durable device_uid and browser metadata in all forms on the page
 * @param {string} deviceUid - The durable device UUID
 * @param {object} browserData - Browser data object from collectBrowserData()
 * @param {object} capabilities - Capability object from collectCapabilities()
 * @returns {void}
 */
export function setDeviceUidInForms(deviceUid, browserData, capabilities) {
    const capabilityJson = serializeCapabilities(capabilities);
    const resolution = `${browserData.screen_width}x${browserData.screen_height}`;
    const uid = persistDeviceUid(deviceUid) || deviceUid;

    setInputValue('device_uid', uid);
    setInputValue('user_agent', browserData.user_agent);
    setInputValue('screen_resolution', resolution);
    setInputValue('device_capabilities', capabilityJson);

    setInputValue('passwordLoginModalDeviceUid', uid);
    setInputValue('passwordLoginModalUserAgent', browserData.user_agent);
    setInputValue('passwordLoginModalScreenResolution', resolution);
    setInputValue('passwordLoginModalCapabilities', capabilityJson);

    setInputValue('passwordFormDeviceUid', uid);
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

exposeDeviceIdentityGlobals();
