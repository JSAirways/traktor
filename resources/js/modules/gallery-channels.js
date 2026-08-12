/**
 * Gallery Channels Module
 * Handles channel selection, content type filtering, and URL management
 * 
 * Navigation Pattern:
 * - Client-side navigation (History API + filterContent): Used for same-page filtering operations
 *   - Channel switching within gallery view
 *   - Content type filtering (videos/playlists)
 *   - Browser back/forward navigation (popstate)
 *   - Benefits: Fast, smooth, preserves scroll position
 * 
 * - Page refresh (window.location.href): Used for cross-view navigation
 *   - Playlist → Gallery (back button)
 *   - Player → Gallery
 *   - Channel switching from playlist view
 *   - Benefits: Reliable, simple, server always renders correct content
 * 
 * URL is the single source of truth - all filtering reads from URL parameters.
 */

import { appState } from '../core/state.js';
import { eventEmitter } from '../core/events.js';
import { TimingConstants } from '../core/constants.js';
import { 
    getScriptData, 
    getScriptDataJson, 
    buildQueryString, 
    getTranslation, 
    updateElementText, 
    toggleVisibility, 
    debounce 
} from '../core/utils.js';

export class GalleryChannels {
    constructor() {
        // Note: currentChannelId and currentContentType are now only used as cache for UI updates
        // URL is the single source of truth - always read from URL in filterContent()
        this.currentChannelId = 'all';
        this.currentContentType = 'all';
        this.offcanvasInstance = null;
        this.channels = [];
        this.username = null;
        
        // Debounce filter operations to reduce unnecessary re-renders
        this.debouncedFilterContent = debounce ? debounce(() => {
            this.filterContent();
        }, TimingConstants?.GALLERY_FILTER_DEBOUNCE || 300) : () => {
            this.filterContent();
        };
        
        this.init();
    }
    
    init() {
        // Wait for DOM to be ready before initializing
        const initWhenReady = () => {
            // Load channel data from script tag
            this.loadChannelData();
            
            // Set up event listeners
            this.setupChannelListeners();
            this.setupFilterListeners();
            this.setupOffcanvas();
            this.setupBrowserNavigation();
            
            // Listen for gallery loaded event - just filter based on current URL
            if (eventEmitter?.on) {
                eventEmitter.on('gallery:loaded', () => {
                    // Filter content based on current URL (single source of truth)
                    requestAnimationFrame(() => {
                        this.filterContent();
                    });
                });
            }
            
            // Initial filter based on URL
            requestAnimationFrame(() => {
                this.filterContent();
            });
        };
        
        // Actually call initWhenReady when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initWhenReady);
        } else {
            // DOM already ready
            initWhenReady();
        }
    }
    
    /**
     * Load channel data from script tag
     */
    loadChannelData() {
        this.channels = getScriptDataJson?.('data-channels', []) || [];
        this.username = getScriptData?.('data-username') || null;
    }
    
    /**
     * Get channel and content type from URL (single source of truth)
     * Returns default values if not in URL
     */
    getChannelFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        let channelId = urlParams.get('channel');
        
        // If no channel specified and "All content" is hidden, default to first channel
        if (!channelId && this.channels.length > 0) {
            const firstChannel = this.channels[0];
            if (firstChannel.id !== 'all') {
                channelId = firstChannel.id;
            } else {
                channelId = 'all';
            }
        }
        
        // If "All content" is hidden and URL explicitly requested 'all', use first channel instead
        if (channelId === 'all' && this.channels.length > 0) {
            const firstChannel = this.channels[0];
            if (firstChannel.id !== 'all') {
                channelId = firstChannel.id;
            }
        }
        
        return channelId || 'all';
    }
    
    /**
     * Get content type from URL
     */
    getContentTypeFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('type') || 'all';
    }
    
    /**
     * Set up channel selection listeners
     */
    setupChannelListeners() {
        // Delegate to channel buttons (both sidebar and offcanvas)
        document.addEventListener('click', (e) => {
            // Check if clicked element itself has data-channel-id (most common case)
            let channelButton = null;
            if (e.target?.hasAttribute?.('data-channel-id')) {
                channelButton = e.target;
            }
            // Check if clicked element is a button with data-channel-id
            if (!channelButton && e.target?.tagName === 'BUTTON' && e.target.hasAttribute?.('data-channel-id')) {
                channelButton = e.target;
            }
            // Try closest if not found
            if (!channelButton && e.target?.closest) {
                channelButton = e.target.closest('[data-channel-id]');
            }
            // Also try closest with button selector
            if (!channelButton && e.target?.closest) {
                channelButton = e.target.closest('button[data-channel-id]');
            }
            // Fallback for older browsers that don't support closest
            if (!channelButton && e.target?.parentElement) {
                let parent = e.target.parentElement;
                let depth = 0;
                while (parent && parent !== document.body && depth < 10) {
                    if (parent.hasAttribute?.('data-channel-id')) {
                        channelButton = parent;
                        break;
                    }
                    // Also check if parent is a button
                    if (parent.tagName === 'BUTTON' && parent.hasAttribute?.('data-channel-id')) {
                        channelButton = parent;
                        break;
                    }
                    parent = parent.parentElement;
                    depth++;
                }
            }
            if (channelButton) {
                e.preventDefault();
                e.stopPropagation();
                const channelId = channelButton.getAttribute('data-channel-id');
                if (channelId) {
                    this.selectChannel(channelId);
                }
            }
        });
    }
    
    /**
     * Set up filter pill listeners
     */
    setupFilterListeners() {
        // Set up listeners for both main and landscape filter pills
        this.setupFilterPillsContainer('contentFilterPills');
        this.setupFilterPillsContainer('contentFilterPillsLandscape');
    }
    
    /**
     * Set up filter listeners for a specific filter pills container
     */
    setupFilterPillsContainer(containerId) {
        // Get filter pills container
        const filterPillsContainer = document.getElementById(containerId);
        if (!filterPillsContainer) {
            // If container doesn't exist, skip (might not be on this page)
            return;
        }
        
        // Remove existing listener if present (prevent duplicates)
        if (filterPillsContainer.hasAttribute('data-filter-listener-attached')) {
            return;
        }
        filterPillsContainer.setAttribute('data-filter-listener-attached', 'true');
        
        // Attach direct listeners to buttons as fallback
        const filterButtons = filterPillsContainer.querySelectorAll('button[data-content-type]');
        for (const btn of filterButtons) {
            if (!btn.hasAttribute('data-direct-listener-attached')) {
                btn.setAttribute('data-direct-listener-attached', 'true');
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const contentType = btn.getAttribute('data-content-type');
                    if (contentType) {
                        this.selectContentType(contentType);
                    }
                    return false;
                }, false);
            }
        }
        
        // Delegate to filter pills container (primary handler)
        filterPillsContainer.addEventListener('click', (e) => {
            // Check if clicked element itself has data-content-type (most common case)
            let filterButton = null;
            if (e.target?.hasAttribute?.('data-content-type')) {
                filterButton = e.target;
            }
            // Check if clicked element is a button with data-content-type
            if (!filterButton && e.target?.tagName === 'BUTTON' && e.target.hasAttribute?.('data-content-type')) {
                filterButton = e.target;
            }
            // Try closest if not found (works for clicks on button or its children)
            if (!filterButton && e.target?.closest) {
                filterButton = e.target.closest('[data-content-type]');
            }
            // Also try closest with button selector
            if (!filterButton && e.target?.closest) {
                filterButton = e.target.closest('button[data-content-type]');
            }
            // Fallback for older browsers that don't support closest
            if (!filterButton && e.target?.parentElement) {
                let parent = e.target.parentElement;
                let depth = 0;
                while (parent && parent !== document.body && depth < 10) {
                    if (parent.hasAttribute?.('data-content-type')) {
                        filterButton = parent;
                        break;
                    }
                    // Also check if parent is a button
                    if (parent.tagName === 'BUTTON' && parent.hasAttribute?.('data-content-type')) {
                        filterButton = parent;
                        break;
                    }
                    // Check if parent is an <li> and find button inside it
                    if (parent.tagName === 'LI') {
                        const buttonInLi = parent.querySelector?.('button[data-content-type]');
                        if (buttonInLi) {
                            filterButton = buttonInLi;
                            break;
                        }
                    }
                    parent = parent.parentElement;
                    depth++;
                }
            }
            if (filterButton) {
                e.preventDefault();
                e.stopPropagation();
                const contentType = filterButton.getAttribute('data-content-type');
                if (contentType) {
                    this.selectContentType(contentType);
                }
                return false; // Additional prevention for older browsers
            }
        }, false); // Use capture phase for better compatibility
    }
    
    /**
     * Set up mobile offcanvas
     */
    setupOffcanvas() {
        const offcanvasElement = document.getElementById('channelSidebarOffcanvas');
        if (!offcanvasElement || !window.bootstrap) return;
        
        // Get or create Bootstrap offcanvas instance
        const Offcanvas = window.bootstrap.Offcanvas;
        if (!Offcanvas) return;
        
        this.offcanvasInstance = Offcanvas.getInstance(offcanvasElement) || 
                                 new Offcanvas(offcanvasElement);
        
        // Cleanup duplicate backdrops (similar to options-menu-offcanvas pattern)
        offcanvasElement.addEventListener('shown.bs.offcanvas', () => {
            this.cleanupBackdrops();
        });
    }
    
    /**
     * Cleanup duplicate Bootstrap backdrops
     * Note: This is an exception to the "no DOM manipulation" rule - it's necessary
     * to fix Bootstrap's own dynamically created backdrop elements that can duplicate.
     * This pattern is consistent with options-menu-offcanvas.js
     */
    cleanupBackdrops() {
        const backdrops = document.querySelectorAll('.offcanvas-backdrop');
        if (backdrops.length > 1) {
            for (let i = 0; i < backdrops.length - 1; i++) {
                backdrops[i].remove();
            }
        }
    }
    
    /**
     * Set up browser back/forward navigation
     */
    setupBrowserNavigation() {
        window.addEventListener('popstate', () => {
            // URL changed - just filter based on new URL
            this.filterContent();
        });
    }
    
    /**
     * Select a channel
     * @param {string} channelId - Channel ID to select ('all' for all videos)
     */
    selectChannel(channelId) {
        // Don't switch channels if we're in player view - user should finish watching first
        const currentView = appState?.get('currentView');
        if (currentView === 'player') {
            return;
        }
        
        // If we're currently viewing a playlist, exit playlist view first
        // Use page refresh for cross-view navigation (playlist → gallery)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('playlist')) {
            const slug = (appState?.get('currentSlug')) || (getScriptData?.('data-slug'));
            if (slug) {
                const newUrlParams = new URLSearchParams();
                if (channelId !== 'all') {
                    newUrlParams.set('channel', channelId);
                }
                // Remove playlist and video parameters
                newUrlParams.delete('playlist');
                newUrlParams.delete('video');
                const newUrl = newUrlParams.toString() 
                    ? `/${slug}/gallery?${newUrlParams.toString()}`
                    : `/${slug}/gallery`;
                // Page refresh - server will render correct content based on URL
                window.location.href = newUrl;
            }
            return;
        }
        
        // Update URL - filterContent() will handle the rest
        const slug = (appState?.get('currentSlug')) || (getScriptData?.('data-slug'));
        if (!slug) return;
        
        const newUrlParams = new URLSearchParams();
        if (channelId !== 'all') {
            newUrlParams.set('channel', channelId);
        }
        // Remove type parameter (reset to 'all')
        newUrlParams.delete('type');
        // Remove playlist parameter if present
        newUrlParams.delete('playlist');
        
        const newPath = `/${slug}/gallery`;
        const queryString = newUrlParams.toString();
        const newUrl = queryString ? `${newPath}?${queryString}` : newPath;
        
        window.history.pushState({ channelId }, '', newUrl);
        
        // Filter content based on new URL
        this.filterContent();
        
        // Close offcanvas on mobile
        if (this.offcanvasInstance?.hide) {
            this.offcanvasInstance.hide();
        }
        
        // Emit event
        if (eventEmitter?.emit) {
            eventEmitter.emit('channel:selected', channelId);
        }
    }
    
    /**
     * Select content type filter
     * @param {string} contentType - Content type: 'videos'|'playlists'
     */
    selectContentType(contentType) {
        // Get current content type from URL
        const currentType = this.getContentTypeFromUrl();
        
        // Toggle: if same type clicked, show all
        if (currentType === contentType) {
            contentType = 'all';
        }
        
        // Update URL - filterContent() will handle the rest
        const slug = (appState?.get('currentSlug')) || (getScriptData?.('data-slug'));
        if (!slug) return;
        
        const urlParams = new URLSearchParams(window.location.search);
        if (contentType !== 'all') {
            urlParams.set('type', contentType);
        } else {
            urlParams.delete('type');
        }
        
        const newPath = `/${slug}/gallery`;
        const queryString = urlParams.toString();
        const newUrl = queryString ? `${newPath}?${queryString}` : newPath;
        
        window.history.pushState({ contentType }, '', newUrl);
        
        // Filter content based on new URL (debounced)
        this.debouncedFilterContent();
        
        // Emit event
        if (eventEmitter?.emit) {
            eventEmitter.emit('content-type:changed', contentType);
        }
    }
    
    /**
     * Update active states based on current URL
     */
    updateActiveStatesFromUrl() {
        const channelId = this.getChannelFromUrl();
        const contentType = this.getContentTypeFromUrl();
        
        // Cache values for UI updates
        this.currentChannelId = channelId;
        this.currentContentType = contentType;
        
        // Update channel buttons
        const channelSidebar = document.querySelector('.channel-sidebar-list, #channelSidebarOffcanvas');
        if (channelSidebar) {
            const buttons = channelSidebar.querySelectorAll('[data-channel-id]');
            for (const button of buttons) {
                const buttonChannelId = button.getAttribute('data-channel-id');
                if (buttonChannelId === channelId) {
                    button.classList.add('active', 'bg-light', 'text-success');
                } else {
                    button.classList.remove('active', 'bg-light', 'text-success');
                }
            }
        }
        
        // Update filter pills
        const filterButtons = document.querySelectorAll('[data-content-type]');
        for (const filterButton of filterButtons) {
            const buttonContentType = filterButton.getAttribute('data-content-type');
            if (buttonContentType === contentType) {
                filterButton.classList.remove('bg-light', 'text-dark');
                filterButton.classList.add('bg-success', 'text-light');
            } else {
                filterButton.classList.remove('bg-success', 'text-light');
                filterButton.classList.add('bg-light', 'text-dark');
            }
        }
    }
    
    /**
     * Update title based on current URL
     */
    updateTitleFromUrl() {
        const channelId = this.getChannelFromUrl();
        const channelName = this.getChannelName(channelId);
        const channelThumbnail = this.getChannelThumbnail(channelId);
        
        // Update channel name header (if exists)
        if (updateElementText) {
            updateElementText('channelNameHeader', channelName);
        }
        
        // Update channel thumbnail (if exists)
        const channelAvatarWithImage = document.getElementById('channelAvatarWithImage');
        const channelAvatarWithIcon = document.getElementById('channelAvatarWithIcon');
        const channelThumbnailImage = document.getElementById('channelThumbnailImage');
        
        if (channelAvatarWithImage && channelAvatarWithIcon) {
            if (channelThumbnail) {
                // Show image avatar, hide icon avatar
                if (toggleVisibility) {
                    toggleVisibility('channelAvatarWithImage', true);
                    toggleVisibility('channelAvatarWithIcon', false);
                }
                
                // Update image source and alt text
                if (channelThumbnailImage) {
                    channelThumbnailImage.src = channelThumbnail;
                    channelThumbnailImage.alt = channelName;
                }
            } else {
                // Show icon avatar, hide image avatar
                if (toggleVisibility) {
                    toggleVisibility('channelAvatarWithImage', false);
                    toggleVisibility('channelAvatarWithIcon', true);
                }
            }
        }
        
        // Update page title
        if (this.username) {
            const baseTitle = `${this.username}'s Traktor`;
            if (channelId === 'all' || !channelId) {
                document.title = baseTitle;
            } else {
                document.title = `${channelName} - ${baseTitle}`;
            }
        }
    }
    
    /**
     * Update active states in UI (class toggling only)
     * @deprecated Use updateActiveStatesFromUrl() instead - reads from URL directly
     */
    updateActiveStates() {
        // Delegate to URL-based method
        this.updateActiveStatesFromUrl();
    }
    
    /**
     * Filter content client-side based on URL parameters (single source of truth)
     */
    filterContent() {
        const galleryContent = document.getElementById('galleryContent');
        if (!galleryContent) {
            return;
        }
        
        // Read channel and content type directly from URL - single source of truth
        const urlParams = new URLSearchParams(window.location.search);
        const channelId = this.getChannelFromUrl();
        const contentType = this.getContentTypeFromUrl();
        const isInPlaylistView = urlParams.has('playlist');
        
        // Cache values for UI updates (updateActiveStates, updateTitle)
        this.currentChannelId = channelId;
        this.currentContentType = contentType;
        
        // Ensure gallery content is visible
        if (galleryContent.style.display === 'none') {
            galleryContent.style.display = '';
        }
        galleryContent.style.visibility = 'visible';
        galleryContent.style.opacity = '1';
        
        // If in playlist view, show all tiles
        if (isInPlaylistView) {
            const tiles = galleryContent.querySelectorAll('[data-channel-id]');
            for (const tile of tiles) {
                let columnWrapper = tile.closest?.('.col-sm-6, .col-md-4');
                if (!columnWrapper && tile.parentElement) {
                    // Fallback for older browsers
                    let parent = tile.parentElement;
                    while (parent && parent !== document.body) {
                        if (parent.classList?.contains('col-sm-6') || parent.classList?.contains('col-md-4')) {
                            columnWrapper = parent;
                            break;
                        }
                        parent = parent.parentElement;
                    }
                }
                if (columnWrapper) {
                    columnWrapper.classList.remove('d-none');
                }
            }
            // Update UI state
            this.updateActiveStatesFromUrl();
            this.updateTitleFromUrl();
            return;
        }
        
        // Filter tiles based on URL parameters
        const allTiles = galleryContent.querySelectorAll('[data-channel-id]');
        
        if (allTiles.length === 0) {
            // No tiles found - might be loading or empty
            // Still update UI state
            this.updateActiveStatesFromUrl();
            this.updateTitleFromUrl();
            return;
        }
        
        for (const tile of allTiles) {
            const tileChannelId = tile.getAttribute('data-channel-id') || 'all';
            const tileContentType = tile.getAttribute('data-content-type') || 'all';
            
            // Filter by channel (from URL)
            const channelMatch = channelId === 'all' || tileChannelId === channelId;
            
            // Filter by content type (from URL)
            const typeMatch = contentType === 'all' || tileContentType === contentType;
            
            // Find parent column wrapper
            let columnWrapper = tile.closest?.('.col-sm-6, .col-md-4');
            if (!columnWrapper && tile.parentElement) {
                // Fallback for older browsers
                let parent = tile.parentElement;
                while (parent && parent !== document.body) {
                    if (parent.classList?.contains('col-sm-6') || parent.classList?.contains('col-md-4')) {
                        columnWrapper = parent;
                        break;
                    }
                    parent = parent.parentElement;
                }
            }
            if (!columnWrapper) continue;
            
            // Toggle visibility based on URL parameters
            if (channelMatch && typeMatch) {
                columnWrapper.classList.remove('d-none');
            } else {
                columnWrapper.classList.add('d-none');
            }
        }
        
        // Update UI state based on URL
        this.updateActiveStatesFromUrl();
        this.updateTitleFromUrl();
    }
    
    /**
     * Get channel name by ID
     * @param {string} channelId - Channel ID
     * @returns {string} Channel name or "All Videos" if channelId is 'all'
     */
    getChannelName(channelId) {
        if (channelId === 'all' || !channelId) {
            return getTranslation?.('gallery.all_videos', 'All Videos') || 'All Videos';
        }
        
        for (const channel of this.channels) {
            if (channel.id === channelId) {
                return channel.name;
            }
        }
        return 'All Videos';
    }
    
    /**
     * Get channel thumbnail by ID
     * @param {string} channelId - Channel ID
     * @returns {string|null} Channel thumbnail URL or null if 'all' or not found
     */
    getChannelThumbnail(channelId) {
        if (channelId === 'all' || !channelId) {
            return null;
        }
        
        for (const channel of this.channels) {
            if (channel.id === channelId) {
                return channel.thumbnail;
            }
        }
        return null;
    }
    
    /**
     * Update page title, channel name header, and thumbnail when channel changes
     * @deprecated Use updateTitleFromUrl() instead - reads from URL directly
     * This method is kept for backward compatibility but should not be used in new code.
     */
    updateTitle() {
        // Delegate to URL-based method
        this.updateTitleFromUrl();
    }
}

// Create instance and export
// Only create if not already created (prevent duplicate initialization)
let galleryChannels = null;
if (typeof window === 'undefined' || !window.Traktor?.Modules?.galleryChannels) {
    galleryChannels = new GalleryChannels();
    
    // Also attach to global namespace for backward compatibility during transition
    if (typeof window !== 'undefined') {
        if (!window.Traktor) {
            window.Traktor = {};
        }
        if (!window.Traktor.Modules) {
            window.Traktor.Modules = {};
        }
        window.Traktor.Modules.GalleryChannels = GalleryChannels;
        window.Traktor.Modules.galleryChannels = galleryChannels;
    }
}

export { galleryChannels };
