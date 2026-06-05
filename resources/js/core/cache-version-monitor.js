/**
 * Cache Version Monitor
 * Monitors API responses for cache version changes and reloads page when content is updated
 * 
 * This ensures all content changes (channel ordering, visibility, new content, etc.)
 * are reflected immediately across all devices without requiring manual refresh.
 * 
 * How it works:
 * 1. On page load, stores the initial cache version from the page HTML (data-cache-version)
 * 2. Monitors appState.cacheVersion updates from API responses
 * 3. When cache version changes, reloads the page to get fresh content
 * 4. Preserves URL state (channel, content type filters) during reload
 */

import { appState } from './state.js';
import { getScriptData } from './utils.js';

export class CacheVersionMonitor {
    constructor() {
        this.pageCacheVersion = null;
        this.isReloading = false;
        this.unsubscribe = null;
        this.init();
    }
    
    /**
     * Initialize cache version monitoring
     */
    init() {
        // Get initial cache version from page HTML (script tag)
        if (getScriptData) {
            this.pageCacheVersion = getScriptData('data-cache-version');
        }
        
        // Only monitor if we have an initial cache version
        if (!this.pageCacheVersion) {
            return;
        }
        
        // Store in appState for reference
        if (appState) {
            appState.set('pageCacheVersion', this.pageCacheVersion);
        }
        
        // Subscribe to cacheVersion changes in appState
        // This is updated by gallery.js when API responses include X-Cache-Version header
        if (appState) {
            this.unsubscribe = appState.subscribe((newState, prevState) => {
                // Check if cacheVersion changed
                const newCacheVersion = newState.cacheVersion;
                const prevCacheVersion = prevState.cacheVersion;
                
                // Only handle if cacheVersion exists, changed, and differs from page version
                if (newCacheVersion && 
                    newCacheVersion !== prevCacheVersion &&
                    newCacheVersion !== this.pageCacheVersion) {
                    this.handleCacheVersionChange(newCacheVersion);
                }
            });
        }
    }
    
    /**
     * Handle cache version change from API response
     * @param {string} newCacheVersion - New cache version from API
     */
    handleCacheVersionChange(newCacheVersion) {
        // Prevent multiple reloads
        if (this.isReloading) {
            return;
        }
        
        // If cache version changed, content has been updated - reload page
        if (newCacheVersion && newCacheVersion !== this.pageCacheVersion) {
            this.isReloading = true;
            
            // Preserve current URL state (channel, content type filters)
            // The URL already contains all necessary state (channel, type query params)
            // window.location.reload() will preserve the current URL
            window.location.reload();
        }
    }
    
    /**
     * Get current page cache version
     * @returns {string|null} Current page cache version
     */
    getPageCacheVersion() {
        return this.pageCacheVersion;
    }
    
    /**
     * Cleanup (unsubscribe from state changes)
     */
    destroy() {
        if (this.unsubscribe) {
            this.unsubscribe();
            this.unsubscribe = null;
        }
    }
}

// Export singleton instance
// Only create instance if we're on a gallery page (has data-cache-version script tag)
let cacheVersionMonitor = null;

// Initialize only if script tag with data-cache-version exists
if (document.querySelector('script[data-cache-version]')) {
    cacheVersionMonitor = new CacheVersionMonitor();
}

export { cacheVersionMonitor };
