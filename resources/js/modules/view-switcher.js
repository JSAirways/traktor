/**
 * View Switcher Module
 * Simple module for toggling between gallery and player views using CSS classes
 */

import { appState } from '../core/state.js';
import { eventEmitter } from '../core/events.js';
import { buildQueryString } from '../core/utils.js';

// Import other modules dynamically to avoid circular dependencies
function getPlaylistStateManager() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.playlistStateManager;
    }
    return null;
}

function getVideoPlayer() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.videoPlayer;
    }
    return null;
}

function getNavbar() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.navbar;
    }
    return null;
}

function getFullscreen() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.fullscreen;
    }
    return null;
}

/**
 * View Switcher - Simple view toggling via CSS classes
 */
export class ViewSwitcher {
    constructor() {
        this.currentView = 'gallery'; // 'gallery' or 'player'
        this.galleryContainer = null;
        this.playerContainer = null;
    }
    
    /**
     * Initialize view switcher
     */
    init() {
        this.galleryContainer = document.querySelector('.gallery-view');
        this.playerContainer = document.querySelector('.player-view');
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', (event) => {
            this.handlePopState(event);
        });
    }
    
    /**
     * Show player view and hide gallery view
     * @param {string} videoId - Video ID to play
     * @param {string} channelId - Channel ID for back navigation
     * @param {object} options - Additional options (playlistId, index, etc.)
     */
    showPlayer(videoId, channelId, options = {}) {
        const slug = appState && appState.get ? appState.get('currentSlug') : null;
        
        if (!slug) {
            console.error('[ViewSwitcher] No slug available');
            return;
        }
        
        // Update view state
        this.currentView = 'player';
        
        // Update body class
        document.body.classList.remove('view-gallery');
        document.body.classList.add('view-player');
        
        // Toggle visibility (class toggling only - following best practices)
        if (this.galleryContainer) {
            this.galleryContainer.classList.add('d-none');
        }
        if (this.playerContainer) {
            this.playerContainer.classList.remove('d-none');
        }
        
        // Show return to gallery button
        const returnToGalleryBtn = document.getElementById('returnToGalleryBtn');
        if (returnToGalleryBtn) {
            returnToGalleryBtn.classList.remove('d-none');
        }
        
        // Set navbar to player view mode
        const navbarInstance = getNavbar();
        if (navbarInstance && navbarInstance.setPlayerViewMode) {
            navbarInstance.setPlayerViewMode(true);
        }
        
        // Update app state - include playlist info if available
        if (appState && appState.setState) {
            const stateUpdate = {
                currentView: 'player',
                currentVideoId: videoId,
                currentChannelId: channelId || 'all'
            };
            
            // If this is a playlist video, preserve playlist state
            const playlistStateManager = getPlaylistStateManager();
            if (options.playlistId) {
                if (playlistStateManager) {
                    const currentPlaylistId = playlistStateManager.getPlaylistId();
                    const currentPlaylistVideos = playlistStateManager.getVideos();
                    
                    // Only update if playlist matches
                    if (currentPlaylistId === parseInt(options.playlistId, 10) && currentPlaylistVideos && currentPlaylistVideos.length > 0) {
                        const videoIndex = options.index !== undefined ? parseInt(options.index, 10) : 0;
                        playlistStateManager.setPlaylist(parseInt(options.playlistId, 10), currentPlaylistVideos, videoIndex);
                        stateUpdate.currentPlaylistId = parseInt(options.playlistId, 10);
                        stateUpdate.currentPlaylistVideos = currentPlaylistVideos;
                        stateUpdate.currentVideoIndex = videoIndex;
                    }
                } else {
                    // Fallback to direct appState access
                    const currentPlaylistVideos = appState.get ? appState.get('currentPlaylistVideos') : null;
                    const currentPlaylistId = appState.get ? appState.get('currentPlaylistId') : null;
                    
                    if (currentPlaylistId === parseInt(options.playlistId, 10) && currentPlaylistVideos && currentPlaylistVideos.length > 0) {
                        stateUpdate.currentPlaylistId = parseInt(options.playlistId, 10);
                        stateUpdate.currentPlaylistVideos = currentPlaylistVideos;
                        stateUpdate.currentVideoIndex = options.index !== undefined ? parseInt(options.index, 10) : 0;
                    }
                }
            } else {
                // Not a playlist video - clear playlist state (only if not in player view)
                if (playlistStateManager) {
                    playlistStateManager.clearPlaylist(); // Will skip if in player view
                }
                stateUpdate.currentPlaylistId = null;
                stateUpdate.currentPlaylistVideos = [];
                stateUpdate.currentVideoIndex = -1;
            }
            
            appState.setState(stateUpdate);
        }
        
        // Update URL using History API
        let newUrl;
        if (options.playlistId) {
            const playlistQuery = buildQueryString ? buildQueryString({
                channel: channelId || 'all',
                index: options.index || 0
            }) : '';
            newUrl = `/${slug}/gallery?playlist=${options.playlistId}${playlistQuery ? '&' + playlistQuery : ''}`;
        } else {
            const channelQuery = channelId && channelId !== 'all' ? `&channel=${channelId}` : '';
            newUrl = `/${slug}/gallery?video=${videoId}${channelQuery}`;
        }
        
        window.history.pushState(
            { view: 'player', videoId: videoId, channelId: channelId, options: options },
            '',
            newUrl
        );
        
        // Ensure player is initialized before attempting to play
        // YouTube API needs the element to be visible for proper initialization
        const playerElement = document.getElementById('player');
        const videoPlayer = getVideoPlayer();
        if (playerElement && videoPlayer) {
            // If player is not initialized yet, initialize it now
            if (!videoPlayer.isReady || !videoPlayer.isReady()) {
                // Player element should now be visible, so initialize
                if (videoPlayer.initialize) {
                    try {
                        videoPlayer.initialize();
                    } catch (e) {
                        // Player initialization failed - will retry on next attempt
                    }
                }
            }
        }
        
        // Size video to fit viewport after view is shown
        setTimeout(() => {
            if (typeof window.sizeVideoToFit === 'function') {
                window.sizeVideoToFit();
            }
        }, 150);
        
        // Emit event for player initialization
        if (eventEmitter && eventEmitter.emit) {
            eventEmitter.emit('view:player-shown', {
                videoId: videoId,
                playlistId: options.playlistId,
                index: options.index,
                channelId: channelId
            });
        }
        
        // Attempt autoplay IMMEDIATELY to preserve user gesture context
        // No delays - user gesture context is lost after ~100ms
        const attemptAutoplay = () => {
            if (!videoPlayer) return;
            
            if (videoPlayer.isReady && videoPlayer.isReady()) {
                // Player ready - load video immediately
                try {
                    if (videoPlayer.loadVideo) {
                        videoPlayer.loadVideo(videoId, true);
                    } else if (videoPlayer.play) {
                        videoPlayer.play();
                    }
                } catch (e) {
                    // Autoplay blocked - user will need to manually start playback
                }
            } else if (eventEmitter && eventEmitter.on) {
                // Player not ready - wait for ready event
                eventEmitter.once('player:ready', () => {
                    if (videoPlayer && videoPlayer.loadVideo) {
                        try {
                            videoPlayer.loadVideo(videoId, true);
                        } catch (e) {
                            // Autoplay blocked
                        }
                    }
                });
            }
        };
        
        attemptAutoplay();
    }
    
    /**
     * Show gallery view and hide player view
     * @param {string} channelId - Channel ID to show (optional)
     * @param {string} contentType - Content type filter (optional)
     */
    showGallery(channelId = 'all', contentType = 'all') {
        const slug = appState && appState.get ? appState.get('currentSlug') : null;
        
        if (!slug) {
            console.error('[ViewSwitcher] No slug available');
            return;
        }
        
        // Exit fullscreen before switching to gallery
        // Check fullscreen state directly using DOM API (more reliable)
        const isFullscreenActive = !!(document.fullscreenElement || document.webkitFullscreenElement || 
                                    document.mozFullScreenElement || document.msFullscreenElement);
        
        const fullscreenInstance = getFullscreen();
        
        if (isFullscreenActive) {
            // Exit fullscreen first, then continue with gallery switch
            if (fullscreenInstance && fullscreenInstance.exit) {
                fullscreenInstance.exit();
            } else {
                // Fallback: exit fullscreen directly if module not available
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
            // Wait a bit for fullscreen to exit before continuing
            setTimeout(() => {
                this._doShowGallery(channelId, contentType);
            }, 150);
            return;
        }
        
        // No fullscreen active, proceed normally
        this._doShowGallery(channelId, contentType);
    }
    
    /**
     * Internal method to actually show gallery view
     * @param {string} channelId - Channel ID to show
     * @param {string} contentType - Content type filter
     */
    _doShowGallery(channelId, contentType) {
        const slug = appState && appState.get ? appState.get('currentSlug') : null;
        
        if (!slug) {
            console.error('[ViewSwitcher] No slug available');
            return;
        }
        
        // Update view state
        this.currentView = 'gallery';
        
        // Update body class
        document.body.classList.remove('view-player');
        document.body.classList.add('view-gallery');
        
        // Toggle visibility (class toggling only - following best practices)
        if (this.playerContainer) {
            this.playerContainer.classList.add('d-none');
        }
        if (this.galleryContainer) {
            this.galleryContainer.classList.remove('d-none');
        }
        
        // Hide return to gallery button
        const returnToGalleryBtn = document.getElementById('returnToGalleryBtn');
        if (returnToGalleryBtn) {
            returnToGalleryBtn.classList.add('d-none');
        }
        
        // Set navbar to gallery view mode
        const navbarInstance = getNavbar();
        if (navbarInstance && navbarInstance.setPlayerViewMode) {
            navbarInstance.setPlayerViewMode(false);
        }
        
        // Stop video when switching back to gallery (must happen before state update)
        const videoPlayer = getVideoPlayer();
        if (videoPlayer && videoPlayer.isReady && videoPlayer.isReady()) {
            try {
                // Use stop() to fully stop playback, not just pause
                if (videoPlayer.stop) {
                    videoPlayer.stop();
                } else if (videoPlayer.pause) {
                    videoPlayer.pause();
                }
            } catch (e) {
                // Silently handle stop errors
            }
        }
        
        // Update app state - clear video state when switching to gallery
        if (appState && appState.setState) {
            const currentPlaylistId = appState.get ? appState.get('currentPlaylistId') : null;
            const currentPlaylistVideos = appState.get ? appState.get('currentPlaylistVideos') : null;
            const currentVideoIndex = appState.get ? appState.get('currentVideoIndex') : -1;
            
            const stateUpdate = {
                currentView: 'gallery',
                currentChannelId: channelId,
                currentContentType: contentType,
                currentVideoId: null // Clear video ID when switching to gallery
            };
            
            // Preserve playlist state when switching to gallery (might be returning from player)
            // Use PlaylistStateManager if available
            const playlistStateManager = getPlaylistStateManager();
            if (playlistStateManager) {
                const playlistId = playlistStateManager.getPlaylistId();
                const videos = playlistStateManager.getVideos();
                const index = playlistStateManager.getCurrentIndex();
                
                if (playlistId && videos && videos.length > 0) {
                    stateUpdate.currentPlaylistId = playlistId;
                    stateUpdate.currentPlaylistVideos = videos;
                    stateUpdate.currentVideoIndex = index;
                } else {
                    stateUpdate.currentPlaylistId = null;
                    stateUpdate.currentPlaylistVideos = [];
                    stateUpdate.currentVideoIndex = -1;
                }
            } else {
                // Fallback
                if (currentPlaylistId && currentPlaylistVideos && currentPlaylistVideos.length > 0) {
                    stateUpdate.currentPlaylistId = currentPlaylistId;
                    stateUpdate.currentPlaylistVideos = currentPlaylistVideos;
                    stateUpdate.currentVideoIndex = currentVideoIndex;
                } else {
                    stateUpdate.currentPlaylistId = null;
                    stateUpdate.currentPlaylistVideos = [];
                    stateUpdate.currentVideoIndex = -1;
                }
            }
            
            appState.setState(stateUpdate);
        }
        
        // Update URL using History API - ensure no video/playlist parameters remain
        const queryParams = {};
        if (channelId && channelId !== 'all') {
            queryParams.channel = channelId;
        }
        if (contentType && contentType !== 'all') {
            queryParams.type = contentType;
        }
        // Explicitly exclude video and playlist parameters
        // Do not include video= or playlist= in URL when showing gallery
        const queryString = buildQueryString ? buildQueryString(queryParams) : '';
        const newUrl = `/${slug}/gallery${queryString ? '?' + queryString : ''}`;
        
        window.history.pushState(
            { view: 'gallery', channelId: channelId, contentType: contentType },
            '',
            newUrl
        );
        
        // Emit event for gallery initialization
        if (eventEmitter && eventEmitter.emit) {
            eventEmitter.emit('view:gallery-shown', {
                channelId: channelId,
                contentType: contentType
            });
        }
    }
    
    /**
     * Handle browser back/forward buttons
     * @param {PopStateEvent} event
     */
    handlePopState(event) {
        if (!event.state) {
            // No state - default to gallery
            this.showGallery();
            return;
        }
        
        if (event.state.view === 'player') {
            // Show player view
            this.showPlayer(event.state.videoId, event.state.channelId, event.state.options || {});
        } else {
            // Show gallery view
            this.showGallery(event.state.channelId, event.state.contentType);
        }
    }
}

// Create and export singleton instance
export const viewSwitcher = new ViewSwitcher();

// Also attach to global namespace for backward compatibility during transition
if (typeof window !== 'undefined') {
    if (!window.Traktor) {
        window.Traktor = {};
    }
    if (!window.Traktor.Modules) {
        window.Traktor.Modules = {};
    }
    window.Traktor.Modules.ViewSwitcher = ViewSwitcher;
    window.Traktor.Modules.viewSwitcher = viewSwitcher;
}
