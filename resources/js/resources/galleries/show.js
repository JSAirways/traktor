/**
 * Gallery Page JavaScript
 * Handles gallery page initialization and channel/content filtering
 */

// Import gallery modules so they get loaded and instantiated
import '../../modules/gallery.js';
import '../../modules/gallery-channels.js';

import { appState } from '../../core/state.js';
import { eventEmitter } from '../../core/events.js';
import { toggleElementVisibility, hideLoadingSpinner } from '../../core/utils.js';
import { initI18n } from '../../core/i18n.js';

// Import other modules dynamically to avoid circular dependencies
function getGallery() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.gallery;
    }
    return null;
}

function getGalleryChannels() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.galleryChannels;
    }
    return null;
}

/**
 * Hide loading spinner and show gallery
 * IMPORTANT: Content visibility is handled by gallery-channels filtering
 * Don't show content here - let filtering happen first to prevent showing wrong channel
 */
function hideLoadingSpinnerLocal() {
    if (hideLoadingSpinner) {
        hideLoadingSpinner('loadingSpinner');
    }
    
    // Attach tile listeners for initial load
    const gallery = getGallery();
    if (gallery?.attachTileListeners) {
        gallery.attachTileListeners();
    }
    
    // DON'T show galleryContent here - let gallery-channels filter it first
    // This prevents showing all channels before filtering happens
    // Content will be shown by filterContent() after proper filtering
}

/**
 * Setup button click handlers
 */
function setupButtons() {
    // Profile Selection button - return to home page (profile selection)
    const profileSelectionBtn = document.getElementById('profileSelectionBtn');
    if (profileSelectionBtn && !profileSelectionBtn.hasAttribute('data-handler-attached')) {
        profileSelectionBtn.setAttribute('data-handler-attached', 'true');
        profileSelectionBtn.addEventListener('click', () => {
            // Clear viewing session and redirect to home (will redirect to welcome if no device registered)
            window.location.href = '/';
        });
    }

    // Back button - return to main gallery
    const backBtn = document.getElementById('backBtn');
    if (backBtn) {
        backBtn.addEventListener('click', () => {
            const slug = appState?.get('currentSlug');
            if (slug) {
                // Simple page refresh - server will render correct content based on URL
                window.location.href = `/${slug}/gallery`;
            }
        });
    }

    // Single event handler for both playlist back buttons (portrait and landscape)
    // Use event delegation to handle clicks on either button - more reliable
    if (!window._playlistBackHandlerAttached) {
        window._playlistBackHandlerAttached = true;
        document.addEventListener('click', (e) => {
            // Check if click was on either playlist back button or its children
            const button = e.target.closest?.('#playlistBackBtn, #playlistBackBtnLandscape');
            
            if (button) {
                const slug = appState?.get('currentSlug');
                if (slug) {
                    const urlParams = new URLSearchParams(window.location.search);
                    // Preserve channel parameter before deleting playlist/video
                    const channelId = urlParams.get('channel');
                    urlParams.delete('playlist');
                    urlParams.delete('video');
                    // Ensure channel parameter is preserved
                    if (channelId) {
                        urlParams.set('channel', channelId);
                    }
                    const newUrl = urlParams.toString() 
                        ? `/${slug}/gallery?${urlParams.toString()}`
                        : `/${slug}/gallery`;
                    // Simple page refresh - server will render correct content based on URL
                    window.location.href = newUrl;
                }
            }
        });
    }
}

/**
 * Initialize gallery page
 */
function init() {
    // Handle playlist request for channel info
    if (eventEmitter?.on) {
        eventEmitter.on('playlist:request-channel-info', (data) => {
            const galleryChannels = getGalleryChannels();
            const channelThumbnail = galleryChannels?.getChannelThumbnail?.(data.channelId);
            const channelName = galleryChannels?.getChannelName?.(data.channelId);
            if (data.callback) {
                data.callback(channelThumbnail, channelName);
            }
        });
    }
    
    // Note: Channel and content type filtering is handled by gallery-channels module
    // All navigation uses page refresh for cross-view transitions (playlist ↔ gallery)
    // Client-side filtering is used for same-page operations (channel/content type filtering)
    
    // Setup button click handlers
    setupButtons();
    
    // Check if we're in playlist view on initial load and set landscape back button state
    const urlParams = new URLSearchParams(window.location.search);
    const isInPlaylistView = urlParams.has('playlist');
    const playlistBackBtnLandscape = document.getElementById('playlistBackBtnLandscape');
    if (playlistBackBtnLandscape) {
        if (isInPlaylistView) {
            playlistBackBtnLandscape.classList.add('playlist-active-landscape');
        } else {
            playlistBackBtnLandscape.classList.remove('playlist-active-landscape');
        }
    }
    
    // Hide loading spinner after gallery is ready
    hideLoadingSpinnerLocal();
        
    // Emit gallery:loaded event for initial page load (server-rendered content)
    // This ensures gallery-channels can filter content on initial load
    if (eventEmitter?.emit) {
        setTimeout(() => {
            eventEmitter.emit('gallery:loaded');
        }, 100);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Initialize i18n if translations are available
    if (typeof window !== 'undefined' && window.appTranslations && window.appLocale && initI18n) {
        initI18n(window.appTranslations, window.appLocale);
    }
    
    init();
    
    // Fallback: hide spinner after 10 seconds
    setTimeout(() => {
        const loadingSpinner = document.getElementById('loadingSpinner');
        if (loadingSpinner && loadingSpinner.style.display !== 'none') {
            hideLoadingSpinnerLocal();
        }
    }, 10000);
});

export { init, setupButtons, hideLoadingSpinnerLocal };
