/**
 * Global Application JavaScript
 * Entry point for all core JavaScript modules
 * 
 * PS4 minimum compatibility: Loads scripts in dependency order
 * All modules use ES6 module system
 */

// Global namespace setup (creates Traktor namespace for backward compatibility)
import './core/namespace.js';

// Core modules (order matters for dependencies)
import './core/constants.js';
import './core/events.js';
import './core/state.js';
import './core/utils.js';
import './core/view-helpers.js';
import './core/i18n.js';
import './core/error-handler.js';
import './core/bootstrap-js.js'; // Makes Bootstrap available globally
import './core/loading-state-manager.js';
import './core/modal-utils.js';
import './core/device-identity.js';
import './core/device-api.js';
import './core/pwa-installer.js';
import './core/asset-version-checker.js';
import './core/cache-version-monitor.js';

// Import functions directly for initialization
import { initPWA } from './core/pwa-installer.js';
import { checkAssetVersion } from './core/asset-version-checker.js';
import { initOrientationDetection } from './core/orientation.js';
import { collectBrowserData, collectCapabilities } from './core/device-identity.js';
import { refreshCapabilities } from './core/device-api.js';

// Shared resources (available on all pages)
import './resources/shared/locale-switcher.js';

// Initialize PWA and asset version checker on page load
function initApp() {
    // Initialize orientation detection (adds body classes for CSS targeting)
    initOrientationDetection();
    
    // Initialize PWA installer
    initPWA();
    
    // Initialize asset version checker
    checkAssetVersion();
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}

// Make axios available globally (for compatibility with existing code)
import axios from 'axios';
if (typeof window !== 'undefined') {
    window.axios = axios;
    window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
}

// Make SortableJS available globally (for drag and drop functionality)
import Sortable from 'sortablejs';
if (typeof window !== 'undefined') {
    window.Sortable = Sortable;
}

// Handle capability refresh if needed
if (typeof window !== 'undefined') {
    const refreshConfig = window.Traktor
        && window.Traktor.Device
        && window.Traktor.Device.capabilityRefresh;

    if (refreshConfig && refreshConfig.needed && refreshConfig.route) {
        const storageKey = refreshConfig.storageKey || 'traktorDeviceCapabilityRefresh';
        try {
            if (window.sessionStorage && window.sessionStorage.getItem(storageKey) === 'done') {
                // Already refreshed
            } else {
                try {
                    const browserData = collectBrowserData();
                    const capabilities = collectCapabilities(browserData);

                    refreshCapabilities(capabilities, refreshConfig.route, refreshConfig.csrf)
                        .then(() => {
                            refreshConfig.needed = false;
                            try {
                                if (window.sessionStorage) {
                                    window.sessionStorage.setItem(storageKey, 'done');
                                }
                            } catch (e) {
                                // Ignore storage errors
                            }
                        })
                        .catch(() => {
                            // Swallow errors to avoid interrupting the session
                        });
                } catch (e) {
                    // Ignore any runtime errors
                }
            }
        } catch (e) {
            // Ignore storage errors
        }
    }
}
