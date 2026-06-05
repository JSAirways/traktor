/**
 * Gallery Page JavaScript
 * Handles gallery view only - player is on separate page
 */

// Import all required modules so they get loaded and instantiated
import '../../modules/playlist-state-manager.js';
import '../../modules/gallery.js';
import '../../modules/gallery-channels.js';
import '../../modules/navbar.js';

import { appState } from '../../core/state.js';
import { eventEmitter } from '../../core/events.js';
import { getScriptData, getScriptDataJson, hideLoadingSpinner } from '../../core/utils.js';

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

function getNavbar() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.navbar;
    }
    return null;
}

/**
 * Initialize gallery view
 */
function initGallery() {
    // Setup button click handlers
    setupButtons();
    
    // Hide loading spinner and show gallery
    hideLoadingSpinnerLocal();
    
    // Setup event listeners for gallery
    setupGalleryEventListeners();
    
    // Emit gallery:loaded event for initial page load (server-rendered content)
    if (eventEmitter?.emit) {
        setTimeout(() => {
            eventEmitter.emit('gallery:loaded');
        }, 100);
    }
}

/**
 * Setup gallery event listeners
 */
function setupGalleryEventListeners() {
    if (!eventEmitter?.on) return;
    
    const galleryChannels = getGalleryChannels();
    
    // Handle playlist request for channel info
    eventEmitter.on('playlist:request-channel-info', (data) => {
        const channelThumbnail = galleryChannels?.getChannelThumbnail?.(data.channelId);
        const channelName = galleryChannels?.getChannelName?.(data.channelId);
        if (data.callback) {
            data.callback(channelThumbnail, channelName);
        }
    });
    
    // Note: Channel and content type filtering is handled by gallery-channels module
    // All navigation uses page refresh for cross-view transitions (playlist ↔ gallery)
    // Client-side filtering is used for same-page operations (channel/content type filtering)
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
    const gallery = getGallery();
    
    // Profile Selection button - return to home page
    const profileSelectionBtn = document.getElementById('profileSelectionBtn');
    if (profileSelectionBtn && !profileSelectionBtn.hasAttribute('data-handler-attached')) {
        profileSelectionBtn.setAttribute('data-handler-attached', 'true');
        profileSelectionBtn.addEventListener('click', () => {
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
    // Setup buttons
    setupButtons();
    
    // Initialize gallery
    initGallery();
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
