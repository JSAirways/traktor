/**
 * Internationalization (i18n) Module
 * 
 * Provides translation functionality for JavaScript modules.
 * Translations are loaded from Laravel's JSON translation files.
 */

let translations = {};
let currentLocale = 'en';

/**
 * Initialize i18n with translations and locale
 * @param {Object} trans - Translation object from Laravel
 * @param {string} locale - Current locale (e.g., 'en', 'de')
 */
export function initI18n(trans, locale) {
    translations = trans || {};
    currentLocale = locale || 'en';
}

/**
 * Get translation for a key
 * Supports nested keys with dot notation (e.g., 'common.back')
 * Supports parameter replacement (e.g., { username: 'John' })
 * 
 * @param {string} key - Translation key (supports dot notation)
 * @param {Object} params - Parameters to replace in translation
 * @returns {string} Translated string or key if not found
 * 
 * @example
 * t('common.back') // Returns "Back"
 * t('auth.log_in_as', { username: 'John' }) // Returns "Log in as John"
 */
export function t(key, params = {}) {
    // Get translation value
    const keys = key.split('.');
    let value = translations;
    
    for (const k of keys) {
        if (value && typeof value === 'object' && k in value) {
            value = value[k];
        } else {
            // Translation not found, return key
            return key;
        }
    }
    
    if (typeof value !== 'string') {
        return key;
    }
    
    // Replace parameters
    if (Object.keys(params).length > 0) {
        Object.keys(params).forEach(param => {
            value = value.replace(`:${param}`, params[param]);
        });
    }
    
    return value;
}

/**
 * Get current locale
 * @returns {string} Current locale code
 */
export function getLocale() {
    return currentLocale;
}

/**
 * Check if translations are loaded
 * @returns {boolean} True if translations are available
 */
export function isReady() {
    return Object.keys(translations).length > 0;
}
