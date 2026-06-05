/**
 * Asset Version Checker
 * Detects when assets have been updated and prompts user to clear cache
 */

import { showToast, getTranslation } from './utils.js';

const STORAGE_KEY = 'traktor_asset_version';
const DISMISSED_VERSION_KEY = 'traktor_asset_version_dismissed';

/**
 * Get asset version from meta tag
 */
function getServerAssetVersion() {
    const metaTag = document.querySelector('meta[name="asset-version"]');
    return metaTag ? metaTag.getAttribute('content') : '0';
}

/**
 * Get stored client asset version
 */
function getClientAssetVersion() {
    return localStorage.getItem(STORAGE_KEY) || '0';
}

/**
 * Store client asset version
 */
function setClientAssetVersion(version) {
    localStorage.setItem(STORAGE_KEY, version);
    // Clear dismissed version when client version is updated (user clicked update)
    localStorage.removeItem(DISMISSED_VERSION_KEY);
}

/**
 * Get dismissed asset version (version user closed toast for)
 */
function getDismissedAssetVersion() {
    return localStorage.getItem(DISMISSED_VERSION_KEY) || null;
}

/**
 * Store dismissed asset version (when user closes toast)
 */
function setDismissedAssetVersion(version) {
    localStorage.setItem(DISMISSED_VERSION_KEY, version);
}

/**
 * Clear service worker cache and browser cache
 */
async function clearClientCache() {
    // Get current server version before clearing
    const serverVersion = getServerAssetVersion();
    
    // Clear service worker caches
    if ('serviceWorker' in navigator && 'caches' in window) {
        try {
            const cacheNames = await caches.keys();
            const deletePromises = cacheNames.map(cacheName => caches.delete(cacheName));
            await Promise.all(deletePromises);
            
            // Unregister service worker to force re-registration
            if ('serviceWorker' in navigator) {
                const registrations = await navigator.serviceWorker.getRegistrations();
                const unregisterPromises = registrations.map(registration => registration.unregister());
                await Promise.all(unregisterPromises);
            }
            
            // Update localStorage version to match server BEFORE reload
            // This prevents the toast from reappearing after reload
            setClientAssetVersion(serverVersion);
            
            // Reload page to get fresh assets
            window.location.reload();
        } catch (error) {
            console.error('Failed to clear cache:', error);
            // Fallback: update version and reload
            const fallbackVersion = getServerAssetVersion();
            setClientAssetVersion(fallbackVersion);
            window.location.reload();
        }
    } else {
        // No service worker support, just update version and reload
        setClientAssetVersion(serverVersion);
        window.location.reload();
    }
}

/**
 * Show asset update toast
 * This toast persists across page refreshes until user clicks update or close
 */
function showAssetUpdateToast() {
    const message = getTranslation ? getTranslation('messages.assets_updated', 'The App has been updated. Update to accept changes') : 'The App has been updated. Update to accept changes';
    const buttonText = getTranslation ? getTranslation('common.update', 'Update') : 'Update';
    const serverVersion = getServerAssetVersion();
    
    const buttonHtml = `<div class="d-flex justify-content-between align-items-center">
        <span class="text-dark">${message}</span>
        <button class="btn btn-sm btn-dark ms-3" onclick="window.clearClientCache()">
            ${buttonText}
        </button>
    </div>`;
    
    if (showToast) {
        const toast = showToast(buttonHtml, 'warning', 0, true); // 0 = no auto-hide, htmlContent = true
        
        // If toast was created, set up close button handler to track dismissal
        if (toast) {
            let wasDismissed = false;
            
            // Find the close button in the toast
            const closeButton = toast.querySelector('.btn-close');
            if (closeButton) {
                // When close button is clicked, store the dismissed version
                // This prevents the toast from showing again until a new version is available
                closeButton.addEventListener('click', () => {
                    wasDismissed = true;
                    setDismissedAssetVersion(serverVersion);
                }, { once: true });
            }
            
            // Listen for hidden event as a fallback (in case toast is hidden programmatically)
            // Only track dismissal if it wasn't already dismissed and wasn't updated
            toast.addEventListener('hidden.bs.toast', () => {
                // Check if client version was updated (user clicked update button)
                const currentClientVersion = getClientAssetVersion();
                // If client version matches server, user clicked update (don't track dismissal)
                // If client version doesn't match and wasn't dismissed, track dismissal
                if (!wasDismissed && currentClientVersion !== serverVersion) {
                    setDismissedAssetVersion(serverVersion);
                }
            }, { once: true });
        }
    }
}

/**
 * Check asset version and show toast if needed
 * Toast only shows if:
 * 1. Server version doesn't match client version
 * 2. Server version hasn't been dismissed by user
 * 3. Server version is not '0' (invalid)
 */
export function checkAssetVersion() {
    const serverVersion = getServerAssetVersion();
    const clientVersion = getClientAssetVersion();
    const dismissedVersion = getDismissedAssetVersion();
    
    // Show toast if:
    // - Server version doesn't match client version
    // - Server version is not '0' (invalid)
    // - Server version hasn't been dismissed (or is different from dismissed version)
    if (serverVersion !== clientVersion && serverVersion !== '0' && serverVersion !== dismissedVersion) {
        showAssetUpdateToast();
    } else {
        // Update stored version to match server if versions match
        if (serverVersion === clientVersion) {
            setClientAssetVersion(serverVersion);
        }
    }
}

// Make clearClientCache available globally for button onclick
if (typeof window !== 'undefined') {
    window.clearClientCache = clearClientCache;
}
