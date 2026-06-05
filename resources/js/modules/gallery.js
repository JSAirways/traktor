/**
 * Gallery Module
 * Handles gallery content loading, playlist management, and tile interactions
 */

import { appState } from '../core/state.js';
import { eventEmitter } from '../core/events.js';
import { t } from '../core/i18n.js';
import {
    toggleElementVisibility,
    makeRequest,
    showLoadingSpinner,
    hideLoadingSpinner,
    getScriptData,
    getScriptDataJson,
    buildQueryString,
    getTranslation,
    parseIntSafe,
    updateElementText
} from '../core/utils.js';

// Import other modules dynamically to avoid circular dependencies
function getPlaylistStateManager() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.playlistStateManager;
    }
    return null;
}

function getFullscreen() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.fullscreen;
    }
    return null;
}


function getPlaylist() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.playlist;
    }
    return null;
}

export class Gallery {
    constructor() {
        this.splashScreen = document.querySelector('.gallery-view');
        this.galleryContent = document.getElementById('galleryContent');
        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.init();
            });
        } else {
            // DOM already ready
            this.init();
        }
    }
    
    init() {
        // Get slug from script tag
        const slug = getScriptData?.('data-slug');
        if (slug && appState) {
            appState.set('currentSlug', slug);
        }
        
        // Get cache version from script tag
        const cacheVersion = getScriptData?.('data-cache-version') || '0';
        if (appState) {
            appState.set('cacheVersion', cacheVersion);
        }
        
        // Get cat GIFs list from data attribute
        if (getScriptDataJson) {
            if (typeof window !== 'undefined') {
                window.availableCatGifs = getScriptDataJson('data-cat-gifs', []);
            }
        }
        
        // Refresh galleryContent reference (in case it wasn't available during construction)
        if (!this.galleryContent) {
            this.galleryContent = document.getElementById('galleryContent');
        }
        
        // Attach tile click listeners
        if (this.galleryContent) {
            this.attachTileListeners();
        } else {
            setTimeout(() => {
                this.galleryContent = document.getElementById('galleryContent');
                if (this.galleryContent) {
                    this.attachTileListeners();
                }
            }, 100);
        }
    }
    
    // Show loading spinner
    showLoadingSpinner() {
        if (showLoadingSpinner) {
            showLoadingSpinner('loadingSpinner');
        }
    }
    
    // Hide loading spinner
    hideLoadingSpinner() {
        if (hideLoadingSpinner) {
            hideLoadingSpinner('loadingSpinner');
        }
    }
    
    /**
     * Load main gallery
     * @deprecated This method is no longer used - all navigation now uses page refresh for cross-view navigation.
     * Client-side filtering is used for same-page operations (channel/content type filtering).
     * Kept for backward compatibility but should not be called.
     */
    async loadMainGallery(channelId = null, contentType = null) {
        if (!appState) return;
        const slug = appState.get('currentSlug');
        if (!slug) return;
        
        // Exit fullscreen if active (when switching from player view)
        const isFullscreenActive = !!(document.fullscreenElement || document.webkitFullscreenElement || 
                                    document.mozFullScreenElement || document.msFullscreenElement);
        if (isFullscreenActive) {
            const fullscreenInstance = getFullscreen();
            if (fullscreenInstance?.exit) {
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
        }
        
        // Read channel from URL if not provided (URL is single source of truth)
        if (channelId === null) {
            const urlParams = new URLSearchParams(window.location.search);
            channelId = urlParams.get('channel') || 'all';
        }
        
        // Always reset content type to 'all' when loading main gallery (show both videos and playlists)
        if (contentType === null) {
            contentType = 'all';
        }
        
        // Hide gallery content immediately to prevent flash of old content
        if (this.galleryContent) {
            this.galleryContent.style.display = 'none';
        }
        
        // Hide playlist header and remove state classes
        // Use CSS classes for responsive behavior - JavaScript only manages state
        const playlistHeader = document.getElementById('playlistHeader');
        const playlistBackBtn = document.getElementById('playlistBackBtn');
        const playlistBackBtnLandscape = document.getElementById('playlistBackBtnLandscape');
        
        if (playlistHeader) {
            playlistHeader.classList.remove('playlist-active');
            playlistHeader.classList.add('d-none');
        }
        
        // Show channel header and filter pills, hide back button
        if (playlistBackBtn) {
            playlistBackBtn.classList.add('d-none');
        }
        if (playlistBackBtnLandscape) {
            playlistBackBtnLandscape.classList.remove('playlist-active-landscape');
        }
        if (toggleElementVisibility) {
            toggleElementVisibility('channelHeaderContainer', true);
            toggleElementVisibility('contentFilterPills', true);
        }
        
        // Remove truncation class from channel name header (restore normal styling)
        const channelNameHeader = document.getElementById('channelNameHeader');
        if (channelNameHeader) {
            channelNameHeader.classList.remove('text-truncate');
        }
        
        // Check if we're returning from playlist view
        // Check URL params first, but also check appState in case URL was already updated
        const urlParams = new URLSearchParams(window.location.search);
        const urlHasPlaylist = urlParams.has('playlist');
        
        // Also check appState - if we have a currentPlaylistId, we're likely returning from playlist
        const playlistStateManager = getPlaylistStateManager();
        const hasPlaylistState = playlistStateManager?.getPlaylistId() || appState?.get('currentPlaylistId');
        
        // We're returning from playlist if URL has playlist param OR we have playlist state
        const wasInPlaylistView = urlHasPlaylist || !!hasPlaylistState;
        
        // Use PlaylistStateManager to clear playlist state (it will check view)
        if (playlistStateManager?.clearPlaylist) {
            playlistStateManager.clearPlaylist(); // Will skip if in player view
        } else {
            // Fallback: Only reset playlist state if we're NOT in player view
            const currentView = appState.get('currentView');
            if (currentView !== 'player') {
                appState.setState({
                    currentPlaylistId: null,
                    currentPlaylistVideos: [],
                    currentVideoIndex: -1
                });
            }
        }
        
        // Store wasInPlaylistView in a variable that will be accessible in callbacks
        const returningFromPlaylist = wasInPlaylistView;
        
        // CRITICAL: When returning from playlist view, we MUST reload from API
        // because the gallery content was replaced with playlist video tiles
        // Don't try to filter playlist video tiles - they're not gallery tiles!
        
        // Also check if gallery content has playlist video tiles (all have data-channel-id="all")
        // If all tiles have data-channel-id="all", they're likely playlist video tiles, not gallery tiles
        let hasPlaylistVideoTiles = false;
        if (this.galleryContent) {
            const allTiles = this.galleryContent.querySelectorAll('[data-channel-id]');
            if (allTiles.length > 0) {
                // Check if all tiles have data-channel-id="all" - this indicates playlist video tiles
                const allHaveAllChannel = Array.from(allTiles).every(tile => 
                    tile.getAttribute('data-channel-id') === 'all'
                );
                hasPlaylistVideoTiles = allHaveAllChannel && allTiles.length > 0;
            }
        }
        
        const shouldReloadFromAPI = returningFromPlaylist || hasPlaylistVideoTiles;
        
        // Check if we can use client-side filtering instead of API call
        // If gallery content has tiles with data-channel-id, we can filter client-side
        // BUT: if we're returning from playlist view or have playlist video tiles, always reload from API
        const hasGalleryTiles = !shouldReloadFromAPI && this.galleryContent && 
            this.galleryContent.querySelectorAll('[data-channel-id]').length > 0;
        
        if (hasGalleryTiles) {
            // Use client-side filtering (like channel switching) - no API call needed
            // Update URL using History API (no page reload)
            const newPath = `/${slug}/gallery`;
            const newUrlParams = new URLSearchParams();
            if (channelId && channelId !== 'all') {
                newUrlParams.set('channel', channelId);
            }
            if (contentType && contentType !== 'all') {
                newUrlParams.set('type', contentType);
            }
            // Remove playlist parameter
            newUrlParams.delete('playlist');
            const urlQueryString = newUrlParams.toString();
            const newUrl = urlQueryString ? `${newPath}?${urlQueryString}` : newPath;
            window.history.pushState(
                { channelId: channelId, contentType: contentType },
                '',
                newUrl
            );
            
            // Filter content based on new URL - filterContent() reads from URL directly
            requestAnimationFrame(() => {
                // Emit loaded event - filterContent() will be called by gallery:loaded handler
                if (eventEmitter) {
                    eventEmitter.emit('gallery:loaded');
                }
            });
            
            // Hide loading spinner (no API call, so no loading needed)
            this.hideLoadingSpinner();
        } else {
            // No gallery tiles available, need to load from API
            // Show loading spinner during transition
            this.showLoadingSpinner();
            
            // Hide navigation elements immediately
            if (toggleElementVisibility) {
                toggleElementVisibility('backButtonContainer', false);
                toggleElementVisibility('playlistNavButtons', false);
            }
            
            if (!makeRequest) {
                this.hideLoadingSpinner();
                return;
            }
            
            // Build query parameters
            // Note: When returning from playlist view, contentType should be 'all' to show both videos and playlists
            const queryParams = {};
            if (channelId && channelId !== 'all') {
                queryParams.channel_id = channelId;
            }
            // Always set content_type to 'all' when returning from playlist view to show both videos and playlists
            if (returningFromPlaylist) {
                queryParams.content_type = 'all';
            } else if (contentType && contentType !== 'all') {
                queryParams.content_type = contentType;
            }
            // If contentType is 'all', don't include it in query params (API defaults to 'all')
            
            // Add cache version and timestamp to prevent browser/service worker caching
            queryParams._v = appState.get('cacheVersion') || '0';
            queryParams._t = Date.now();
            
            const queryString = buildQueryString?.(queryParams) || '';
            const url = `/api/user/${slug}/videos?${queryString}`;
            
            try {
                const response = await makeRequest(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'text/html',
                        'Cache-Control': 'no-cache'
                    },
                    responseType: 'html'
                });
                
                // Extract data and headers from response object
                const html = response.data || response; // Backward compatibility fallback
                const headers = response.headers || {};
                
                // Update cache version from response header if provided
                const cacheVersionHeader = headers['x-cache-version'] || null;
                if (!cacheVersionHeader && response.xhr) {
                    // Fallback: try to get from xhr directly
                    const headerValue = response.xhr.getResponseHeader('X-Cache-Version');
                    if (headerValue && appState) {
                        appState.set('cacheVersion', headerValue);
                    }
                } else if (cacheVersionHeader && appState) {
                    appState.set('cacheVersion', cacheVersionHeader);
                }
                
                if (this.galleryContent) {
                    // Note: innerHTML usage is an exception - loading server-rendered HTML from API
                    // Content is still server-rendered (Blade templates), just loaded dynamically
                    this.galleryContent.innerHTML = html;
                    this.galleryContent.style.display = '';
                    this.attachTileListeners();
                    
                    // Update URL using History API (no page reload)
                    // Remove playlist parameter, keep channel parameter
                    const newPath = `/${slug}/gallery`;
                    const urlParams = new URLSearchParams();
                    if (channelId && channelId !== 'all') {
                        urlParams.set('channel', channelId);
                    }
                    if (contentType && contentType !== 'all') {
                        urlParams.set('type', contentType);
                    }
                    const urlQueryString = urlParams.toString();
                    const newUrl = urlQueryString ? `${newPath}?${urlQueryString}` : newPath;
                    window.history.pushState(
                        { channelId: channelId, contentType: contentType },
                        '',
                        newUrl
                    );
                    
                    // Filter content based on URL - filterContent() reads from URL directly
                    requestAnimationFrame(() => {
                        // Emit loaded event - filterContent() will be called by gallery:loaded handler
                        if (eventEmitter) {
                            eventEmitter.emit('gallery:loaded');
                        }
                    });
                }
                
                // Hide loading spinner
                this.hideLoadingSpinner();
            } catch (error) {
                // Handle 403 (unauthorized) - viewing session expired or invalid
                // Check for 403 status before doing anything else
                const isUnauthorized = error?.status === 403 || 
                                     error?.message?.includes('Unauthorized') ||
                                     error?.message?.includes('unauthorized');
                
                if (isUnauthorized) {
                    const slug = appState?.get('currentSlug');
                    if (slug) {
                        // Hide loading spinner before redirect
                        this.hideLoadingSpinner();
                        // Redirect to gallery page - it will handle session validation and PIN entry if needed
                        // Use window.location.replace to prevent back button issues
                        window.location.replace(`/${slug}/gallery`);
                        return; // Exit early to prevent further execution
                    }
                }
                
                // Hide loading spinner on error (for non-403 errors)
                this.hideLoadingSpinner();
                console.error('Error loading gallery:', error);
            }
        }
    }
    
    // Load playlist videos
    async loadPlaylistVideos(playlistId) {
        if (!parseIntSafe || !appState || !makeRequest) {
            this.hideLoadingSpinner();
            return [];
        }
        
        // Ensure playlistId is a valid integer
        const playlistIdInt = parseIntSafe(playlistId);
        if (playlistIdInt === null) {
            const errorMsg = getTranslation?.('gallery.invalid_playlist_id', 'Invalid playlist ID. Please try again.') || 'Invalid playlist ID. Please try again.';
            alert(errorMsg);
            this.hideLoadingSpinner();
            return [];
        }
        
        // Use absolute URL to avoid mobile browser URL resolution issues
        const baseUrl = window.location.origin;
        const cacheVersion = appState.get('cacheVersion') || '0';
        const url = `${baseUrl}/api/playlist/${playlistIdInt}/videos?_v=${cacheVersion}&_t=${Date.now()}`;
        
        try {
            // Request JSON first to catch authorization errors properly
            // HTML requests get redirected on 403, which causes issues
            const jsonResponse = await makeRequest(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                responseType: 'json'
            });
            
            // Extract data and headers from response object
            let data = jsonResponse.data || jsonResponse; // Backward compatibility fallback
            const headers = jsonResponse.headers || {};
            
            // Update cache version from response header if provided
            let cacheVersionHeader = headers['x-cache-version'] || null;
            if (!cacheVersionHeader && jsonResponse.xhr) {
                // Fallback: try to get from xhr directly
                cacheVersionHeader = jsonResponse.xhr.getResponseHeader('X-Cache-Version');
            }
            if (cacheVersionHeader && appState) {
                appState.set('cacheVersion', cacheVersionHeader);
            }
            
            // Also check if cache_version is in JSON response (for backwards compatibility)
            if (data?.cache_version !== undefined && appState) {
                appState.set('cacheVersion', String(data.cache_version));
            }
            
            // Validate JSON response structure
            if (!data || !data.videos || !Array.isArray(data.videos)) {
                const errorMsg = getTranslation?.('gallery.invalid_playlist_data', 'Invalid playlist data received from server.') || 'Invalid playlist data received from server.';
                throw new Error(errorMsg);
            }
            
            // Store data for use in HTML request
            // Only fetch HTML if JSON request succeeded and has valid data
            const htmlCacheBuster = `?_t=${Date.now()}`;
            let html = null;
            
            try {
                const htmlResponse = await makeRequest(url + htmlCacheBuster, {
                    method: 'GET',
                    headers: {
                        'Accept': 'text/html',
                        'Cache-Control': 'no-cache'
                    },
                    responseType: 'html'
                });
                
                // Extract data from response object
                const htmlResponseData = htmlResponse.data || htmlResponse; // Backward compatibility fallback
                
                // Validate HTML response (defensive check for unexpected redirects)
                if (htmlResponseData) {
                    const lowerHtml = htmlResponseData.toLowerCase();
                    // Check for welcome page indicators (should not happen with proper auth, but defensive)
                    if (lowerHtml.includes('welcome') || 
                        lowerHtml.includes('loadingview') || 
                        lowerHtml.includes('userselectionview') ||
                        lowerHtml.includes('passwordloginmodal')) {
                        const message = getTranslation?.('messages.unauthorized_access', 'Unauthorized access. Please authenticate to continue.') || 'Unauthorized access. Please authenticate to continue.';
                        throw new Error(message);
                    }
                    
                    // Verify HTML contains expected gallery content
                    if (lowerHtml.includes('video-tile') || lowerHtml.includes('gallery')) {
                        html = htmlResponseData;
                    }
                }
            } catch (htmlError) {
                // If HTML request fails or is redirected, we can still proceed with JSON data
                // But if it's an authorization error, throw it
                if (htmlError?.message?.includes('Unauthorized')) {
                    throw htmlError;
                }
                // For other HTML errors, log but continue with JSON data only
                console.warn('HTML request failed, continuing with JSON data only:', htmlError);
            }
            
            // Update gallery content with HTML if available and valid
            if (html && this.galleryContent) {
                this.galleryContent.innerHTML = html;
                this.attachTileListeners();
                
                // Defensive check: verify inserted content doesn't contain welcome page elements
                const insertedWelcomeElements = this.galleryContent.querySelectorAll('#loadingView, #userSelectionView, #passwordLoginModal');
                if (insertedWelcomeElements.length > 0) {
                    this.galleryContent.innerHTML = '';
                    const message = getTranslation?.('messages.unauthorized_access', 'Unauthorized access. Please authenticate to continue.') || 'Unauthorized access. Please authenticate to continue.';
                    throw new Error(message);
                }
                
                // Ensure gallery content is visible
                this.galleryContent.style.display = '';
            } else if (!html && this.galleryContent && data.videos && data.videos.length > 0) {
                // Clear content if HTML not available (JSON-only response)
                this.galleryContent.innerHTML = '';
            }
            
            // Show and populate playlist header
            if (data.playlist) {
                // Get playlist's channel_id from URL (single source of truth)
                const urlParams = new URLSearchParams(window.location.search);
                let playlistChannelId = urlParams.get('channel') || appState.get('currentChannelId') || 'all';
                
                // Ensure URL has channel parameter set
                const slug = appState.get('currentSlug');
                if (slug) {
                    urlParams.set('channel', playlistChannelId);
                    // Remove any video params but keep channel
                    urlParams.delete('video');
                    const newUrl = `/${slug}/gallery?${urlParams.toString()}`;
                    window.history.replaceState(
                        { channelId: playlistChannelId, view: 'playlist', playlistId: playlistIdInt },
                        '',
                        newUrl
                    );
                }
                
                // DON'T replace channel name header - keep channel name visible
                // Instead, update playlist header (separate component below channel header)
                // Update playlist title
                if (updateElementText) {
                    updateElementText('playlistTitle', data.playlist.title);
                }
                
                // Show playlist header (mobile), show back button in channel header (replaces filter pills), keep channel header container visible (for title and thumbnail), hide filter pills
                // Use CSS classes for responsive behavior - JavaScript only manages state
                const playlistHeader = document.getElementById('playlistHeader');
                const playlistBackBtn = document.getElementById('playlistBackBtn');
                const playlistBackBtnLandscape = document.getElementById('playlistBackBtnLandscape');
                const contentFilterPills = document.getElementById('contentFilterPills');
                
                if (playlistHeader) {
                    playlistHeader.classList.add('playlist-active');
                    playlistHeader.classList.remove('d-none');
                }
                if (playlistBackBtn) {
                    playlistBackBtn.classList.remove('d-none');
                }
                if (playlistBackBtnLandscape) {
                    playlistBackBtnLandscape.classList.add('playlist-active-landscape');
                }
                if (toggleElementVisibility) {
                    toggleElementVisibility('channelHeaderContainer', true); // Keep visible for title and thumbnail
                    toggleElementVisibility('contentFilterPills', false);
                }
                
                // Update channel thumbnail based on playlist's channel
                // Emit event to request channel info from galleryChannels module
                if (eventEmitter) {
                    eventEmitter.emit('playlist:request-channel-info', {
                        channelId: playlistChannelId,
                        callback: (channelThumbnail, channelName) => {
                            // Update thumbnail visibility
                            const channelAvatarWithImage = document.getElementById('channelAvatarWithImage');
                            const channelAvatarWithIcon = document.getElementById('channelAvatarWithIcon');
                            const channelThumbnailImage = document.getElementById('channelThumbnailImage');
                            
                            if (channelAvatarWithImage && channelAvatarWithIcon) {
                                if (channelThumbnail) {
                                    // Show image avatar, hide icon avatar
                                    toggleElementVisibility('channelAvatarWithImage', true);
                                    toggleElementVisibility('channelAvatarWithIcon', false);
                                    
                                    // Update image source and alt text
                                    if (channelThumbnailImage) {
                                        channelThumbnailImage.src = channelThumbnail;
                                        channelThumbnailImage.alt = channelName;
                                    }
                                } else {
                                    // Show icon avatar, hide image avatar
                                    toggleElementVisibility('channelAvatarWithImage', false);
                                    toggleElementVisibility('channelAvatarWithIcon', true);
                                }
                            }
                        }
                    });
                }
            }
            
            // Update navigation visibility
            if (toggleElementVisibility) {
                toggleElementVisibility('backButtonContainer', false);
                toggleElementVisibility('playlistNavButtons', false);
            }
            
            // Store playlist videos in state using PlaylistStateManager
            const playlistStateManager = getPlaylistStateManager();
            if (playlistStateManager?.setPlaylist) {
                playlistStateManager.setPlaylist(playlistIdInt, data.videos, -1);
            } else if (appState) {
                // Fallback
                appState.setState({
                    currentPlaylistId: playlistIdInt,
                    currentPlaylistVideos: data.videos,
                    currentVideoIndex: -1
                });
            }
            
            // When viewing playlist videos, ensure content type is set to 'videos' and all tiles are visible
            // This prevents filtering from hiding the videos (they have data-content-type="videos")
            // Update immediately to prevent any filtering from hiding content
            if (eventEmitter) {
                eventEmitter.emit('playlist:update-content-type', {
                    contentType: 'videos' // Playlist videos are videos, so set to 'videos' to show them
                });
                
                // Set playlist directly instead of emitting event (better performance, clearer flow)
                const playlistInstance = getPlaylist();
                if (playlistInstance?.setPlaylist) {
                    playlistInstance.setPlaylist(playlistIdInt, data.videos, -1);
                } else if (eventEmitter?.emit) {
                    // Fallback: emit event for backwards compatibility
                    eventEmitter.emit('playlist:loaded', { playlistId: playlistIdInt, videos: data.videos });
                }
            }
            
            // Hide loading spinner on success
            this.hideLoadingSpinner();
            
            return data.videos;
        } catch (error) {
            this.hideLoadingSpinner();
            if (error?.status === 403) {
                const message = getTranslation?.('messages.unauthorized_access', 'Unauthorized access. Please authenticate to continue.') || 'Unauthorized access. Please authenticate to continue.';
                alert(message);
                return [];
            }
            if (error?.status === 404) {
                alert(`Playlist not found (ID: ${playlistIdInt}). It may have been deleted or is not accessible.`);
                return [];
            }
            const errorMsg = error?.message || (getTranslation?.('gallery.playlist_load_failed', 'Failed to load playlist videos.') || 'Failed to load playlist videos.');
            alert(errorMsg);
            return [];
        }
    }
    
    // Attach click listeners to video tiles
    attachTileListeners() {
        // Use event delegation on galleryContent to avoid replaceChild
        // Remove existing listener if present
        if (this.galleryContent) {
            // Check if listener is already attached
            if (!this.galleryContent.hasAttribute('data-tile-listener-attached')) {
                this.galleryContent.setAttribute('data-tile-listener-attached', 'true');
                
                this.galleryContent.addEventListener('click', (e) => {
                    // Find the closest video tile or playlist tile
                    let tile = null;
                    const element = e.target;
                    
                    // Check clicked element itself first
                    if (element?.classList?.contains('video-tile') || element?.classList?.contains('playlist-tile')) {
                        tile = element;
                    }
                    // Check by data-type attribute
                    if (!tile && element?.hasAttribute?.('data-type')) {
                        tile = element;
                    }
                    // Try closest
                    if (!tile && element?.closest) {
                        tile = element.closest('.video-tile, .playlist-tile');
                    }
                    // Fallback for older browsers that don't support closest
                    if (!tile && element?.parentElement) {
                        let parent = element.parentElement;
                        let depth = 0;
                        while (parent && parent !== document.body && depth < 10) {
                            if (parent.classList?.contains('video-tile') || parent.classList?.contains('playlist-tile')) {
                                tile = parent;
                                break;
                            }
                            if (parent.hasAttribute?.('data-type')) {
                                tile = parent;
                                break;
                            }
                            parent = parent.parentElement;
                            depth++;
                        }
                    }
                    if (!tile) return;
                    
                    const type = tile.getAttribute('data-type');
                    const videoId = tile.getAttribute('data-id');
                
                    if (type === 'playlist') {
                        // Load playlist videos (show grid on gallery page - existing behavior)
                        const playlistId = tile.getAttribute('data-id');
                        const playlistIdInt = parseIntSafe?.(playlistId);
                        if (playlistIdInt === null) {
                            alert(getTranslation?.('gallery.invalid_playlist_id', 'Invalid playlist ID. Please try again.') || 'Invalid playlist ID. Please try again.');
                            return;
                        }
                        // Get playlist's channel_id from tile
                        const playlistChannelId = tile.getAttribute('data-channel-id') || 'all';
                        // Update URL to include channel parameter (single source of truth)
                        const slug = appState?.get('currentSlug');
                        if (slug) {
                            const urlParams = new URLSearchParams(window.location.search);
                            urlParams.set('channel', playlistChannelId);
                            // Remove any existing playlist or video params
                            urlParams.delete('playlist');
                            urlParams.delete('video');
                            const newUrl = `/${slug}/gallery?${urlParams.toString()}`;
                            window.history.pushState(
                                { channelId: playlistChannelId, view: 'playlist', playlistId: playlistIdInt },
                                '',
                                newUrl
                            );
                        }
                        // Show loading spinner before loading
                        this.showLoadingSpinner();
                        this.loadPlaylistVideos(playlistIdInt);
                        // Don't auto-play - just show the grid
                    } else if (type === 'video') {
                        // Load player view via AJAX (enables autoplay with user gesture)
                        const videoIndex = tile.getAttribute('data-video-index');
                        const currentPlaylistId = appState?.get('currentPlaylistId');
                        const slug = appState?.get('currentSlug');
                        
                        if (!slug) {
                            const errorMsg = getTranslation?.('gallery.error', 'Error: Unable to determine user.') || 'Error: Unable to determine user.';
                            alert(errorMsg);
                            return;
                        }
                        
                        if (videoIndex !== null && currentPlaylistId) {
                            // We're clicking a video in a playlist view - navigate to playlist player page
                            const parsedIndex = parseIntSafe?.(videoIndex, -1) ?? parseInt(videoIndex, 10);
                            if (parsedIndex >= 0) {
                                // Read channel from URL (single source of truth)
                                const urlParams = new URLSearchParams(window.location.search);
                                const playlistChannelId2 = urlParams.get('channel') || appState?.get('currentChannelId') || 'all';
                                const queryParams = {
                                    channel: playlistChannelId2,
                                    index: parsedIndex
                                };
                                const queryString = buildQueryString?.(queryParams) || '';
                                const url = `/${slug}/player/playlist/${currentPlaylistId}${queryString ? '?' + queryString : ''}`;
                                window.location.href = url;
                            }
                        } else {
                            // Regular video from main gallery - navigate to single video player page
                            const channelId = tile.getAttribute('data-channel-id') || appState?.get('currentChannelId') || 'all';
                            const url = `/${slug}/player/${videoId}${channelId && channelId !== 'all' ? '?channel=' + channelId : ''}`;
                            window.location.href = url;
                        }
                    }
                });
            }
        }
    }
    
    // Show gallery view
    show() {
        if (this.splashScreen) {
            this.splashScreen.classList.remove('d-none');
        }
    }
    
    // Hide gallery view
    hide() {
        if (this.splashScreen) {
            this.splashScreen.classList.add('d-none');
        }
    }
    
    // Show navigation (for gallery visibility)
    showNavigation() {
        if (this.splashScreen) {
            this.splashScreen.style.pointerEvents = 'auto';
            this.splashScreen.style.opacity = '1';
        }
    }
    
    // Hide navigation (dim gallery when video playing)
    hideNavigation() {
        if (this.splashScreen) {
            this.splashScreen.style.pointerEvents = 'none';
            this.splashScreen.style.opacity = '0.3';
        }
    }
    
    /**
     * Set up Intersection Observer for lazy rendering optimization
     * Renders tiles as they come into view for better initial load performance
     */
    setupLazyRendering() {
        if (!this.galleryContent) return;
        
        // Check if Intersection Observer is supported
        if (!('IntersectionObserver' in window)) {
            return; // Fallback: all tiles are already rendered
        }
        
        // Get all tiles that might be below the fold
        const tiles = this.galleryContent.querySelectorAll('.video-tile, .playlist-tile');
        if (tiles.length === 0) return;
        
        // Create observer with root margin for preloading
        const observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    // Tile is visible - ensure it's fully loaded
                    const img = entry.target.querySelector('img');
                    if (img && img.loading === 'lazy' && !img.complete) {
                        // Force image load if it hasn't started
                        img.loading = 'eager';
                    }
                    // Unobserve once visible
                    observer.unobserve(entry.target);
                }
            }
        }, {
            rootMargin: '50px' // Start loading 50px before tile enters viewport
        });
        
        // Observe all tiles (they're already in DOM, just optimizing image loading)
        for (const tile of tiles) {
            observer.observe(tile);
        }
    }
}

// Create and export singleton instance
export const gallery = new Gallery();

// Also attach to global namespace for backward compatibility during transition
if (typeof window !== 'undefined') {
    if (!window.Traktor) {
        window.Traktor = {};
    }
    if (!window.Traktor.Modules) {
        window.Traktor.Modules = {};
    }
    window.Traktor.Modules.Gallery = Gallery;
    window.Traktor.Modules.gallery = gallery;
    
    // Make available globally for backward compatibility
    window.gallery = gallery;
}
