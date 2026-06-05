/**
 * Player Page - Complete implementation from scratch
 * Handles single video and playlist playback on dedicated player page
 */

// Import player modules so they get loaded and instantiated
import '../../modules/video-player.js';
import '../../modules/playlist.js';
import '../../modules/navbar.js';
import '../../modules/fullscreen.js';
import '../../modules/controls.js'; // Import controls module for touch/click handling

import { appState } from '../../core/state.js';
import { eventEmitter } from '../../core/events.js';
import { TimingConstants } from '../../core/constants.js';
import { errorHandler } from '../../core/error-handler.js';
import { getScriptData, getScriptDataJson, parseIntSafe, formatTime, fixMobileViewport, setPlayerViewOverflow, hideLoadingSpinner, buildQueryString, toggleVisibility } from '../../core/utils.js';
import { analyticsTracker } from '../../core/analytics-tracker.js';

// Import other modules dynamically to avoid circular dependencies
function getVideoPlayer() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.videoPlayer;
    }
    return null;
}

function getPlaylist() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.playlist;
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


// DOM elements
let playerView = document.querySelector('.player-view');
let videoContainer = document.getElementById('videoContainer');
let customControlBar = document.getElementById('customControlBar');
let playPauseBtn = document.getElementById('customPlayPause');
let playIcon = playPauseBtn?.querySelector('.play-icon');
let pauseIcon = playPauseBtn?.querySelector('.pause-icon');
let progressBar = document.getElementById('customProgressBar');
let progressFill = document.getElementById('customProgressFill');
let currentTimeEl = document.getElementById('currentTime');
let durationEl = document.getElementById('duration');
let fullscreenBtn = document.getElementById('customFullscreen');
let clickBlocker = document.querySelector('.custom-click-blocker');
let catGifContainer = document.querySelector('.cat-gif-container');
let catGifImg = document.getElementById('catGif');
let videoOverlayEffect = document.querySelector('.video-overlay-effect');
let returnToGalleryBtn = document.getElementById('returnToGalleryBtn');
let prevVideoBtn = document.getElementById('prevVideoBtn');
let nextVideoBtn = document.getElementById('nextVideoBtn');

// Data from script tag
let slug = getScriptData?.('data-slug') || null;
let initialVideoId = getScriptData?.('data-video-id') || null;
let initialVideoDbId = parseIntSafe?.(getScriptData?.('data-video-db-id'), null) || null;
let channelId = getScriptData?.('data-channel-id') || null;
let playlistId = getScriptData?.('data-playlist-id') || null;
let playlistVideos = getScriptDataJson?.('data-playlist-videos', []) || [];
let currentIndex = parseIntSafe?.(getScriptData?.('data-current-index'), 0) || 0;
let catGifs = getScriptDataJson?.('data-cat-gifs', []) || [];

// Make cat gifs available globally for controls module
if (typeof window !== 'undefined') {
    window.availableCatGifs = catGifs;
}

// State
let isDragging = false;
let autoHideTimeout = null;
let progressUpdateInterval = null;
let currentCatGifIndex = 0;

// Cached DOM elements (queried once)
let navbarElement = null;

/**
 * Initialize player page
 */
function init() {
    // Set navbar to player view mode
    const navbar = getNavbar();
    if (navbar?.setPlayerViewMode) {
        navbar.setPlayerViewMode(true);
    }

    // Show return to gallery button (always visible on player page)
    if (returnToGalleryBtn && toggleVisibility) {
        toggleVisibility('returnToGalleryBtn', true);
    }

    // Show playlist navigation buttons if in playlist mode
    if (playlistId && playlistVideos.length > 0 && toggleVisibility) {
        toggleVisibility('playlistNavButtons', true);
    }

    // Prevent horizontal scrollbar
    if (setPlayerViewOverflow) {
        setPlayerViewOverflow(true);
    }

    // Cache navbar element
    navbarElement = document.querySelector('.top-navbar.player-view-mode');
    
    // Fix mobile viewport height
    // Call immediately and also after delay
    if (fixMobileViewport) {
        // Call immediately to ensure elements are visible
        fixMobileViewport();
        // Also call after delay to handle any layout changes
        if (TimingConstants?.MOBILE_VIEWPORT_FIX_DELAY) {
            setTimeout(() => {
                fixMobileViewport();
            }, TimingConstants.MOBILE_VIEWPORT_FIX_DELAY);
        }
    }

    // Initialize playlist if in playlist mode
    const playlist = getPlaylist();
    if (playlistId && playlistVideos.length > 0 && appState?.setState) {
        appState.setState({
            currentPlaylistId: parseInt(playlistId, 10),
            currentPlaylistVideos: playlistVideos,
            currentVideoIndex: currentIndex
        });

        if (playlist?.setPlaylist) {
            playlist.setPlaylist(parseInt(playlistId, 10), playlistVideos, currentIndex);
        }
        if (playlist?.updateNavbar) {
            playlist.updateNavbar();
        }
    } else if (appState?.setState) {
        appState.setState({
            currentPlaylistId: null,
            currentPlaylistVideos: [],
            currentVideoIndex: -1
        });
    }

    // Setup event listeners
    setupEventListeners();

    // Setup controls
    setupControls();

    // Setup keyboard shortcuts
    setupKeyboardShortcuts();

    // Setup fullscreen
    setupFullscreen();

    // Show navbar and controls initially
    // Refresh element references (elements might not have been available when module loaded)
    customControlBar = document.getElementById('customControlBar');
    navbarElement = document.querySelector('.top-navbar.player-view-mode');
    
    // Ensure control bar is visible by removing hidden class (following best practices - class toggling only)
    // Control bar uses position: fixed (like navbar), so it doesn't depend on parent container heights
    if (customControlBar) {
        customControlBar.classList.remove('hidden');
        // Force visibility and positioning immediately
        customControlBar.style.display = 'flex';
        customControlBar.style.visibility = 'visible';
        customControlBar.style.opacity = '1';
        customControlBar.style.position = 'fixed';
        customControlBar.style.bottom = '0';
        customControlBar.style.top = 'auto';
        customControlBar.style.left = '0';
        customControlBar.style.right = '0';
    }
    showControlBar();
    
    // Don't auto-hide immediately on page load - give user time to see controls
    clearAutoHide();
    
    // Simple video sizing to fit viewport
    // CSS handles most cases, but explicit sizing ensures consistency
    sizeVideoToFit();
    
    // Update on resize
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(sizeVideoToFit, 100);
    });
    
    window.addEventListener('orientationchange', () => {
        setTimeout(sizeVideoToFit, 200);
    });
    
    // Try to play video on first user interaction
    // This is a workaround for autoplay being blocked
    let hasTriedAutoplay = false;
    const tryAutoplayOnInteraction = () => {
        const videoPlayer = getVideoPlayer();
        if (!hasTriedAutoplay && videoPlayer?.isReady?.() && videoPlayer.play) {
            hasTriedAutoplay = true;
            try {
                videoPlayer.play();
            } catch (e) {
                // Autoplay still blocked - user will need to click play button
            }
        }
    };
    
    // Try autoplay on first touch/click anywhere on the page
    document.addEventListener('touchstart', tryAutoplayOnInteraction, { once: true, passive: true });
    document.addEventListener('click', tryAutoplayOnInteraction, { once: true, passive: true });
    
    // Get the video ID to play - prefer playlist video if in playlist mode
    const videoIdToPlay = (() => {
        // If we have a playlist with videos, get video ID from playlist
        if (playlistId && playlistVideos && playlistVideos.length > 0 && currentIndex >= 0 && currentIndex < playlistVideos.length) {
            const playlistVideo = playlistVideos[currentIndex];
            if (playlistVideo && playlistVideo.video_id) {
                return playlistVideo.video_id;
            }
        }
        // Otherwise use initialVideoId
        return initialVideoId;
    })();
    
    // Play initial video when player is ready
    if (eventEmitter?.on) {
        eventEmitter.on('player:ready', () => {
            // Ensure controls are visible before playing (class toggling only)
            if (customControlBar) {
                customControlBar.classList.remove('hidden');
            }
            playVideo(videoIdToPlay);
            if (hideLoadingSpinner) {
                hideLoadingSpinner('loadingSpinner');
            }
            // Start progress updates after player is ready
            startProgressUpdate();
        });
    }
    
    // Check if player is already ready
    const videoPlayer = getVideoPlayer();
    if (videoPlayer?.isReady?.()) {
        // Ensure controls are visible before playing (class toggling only)
        if (customControlBar) {
            customControlBar.classList.remove('hidden');
        }
        playVideo(videoIdToPlay);
        if (hideLoadingSpinner) {
            hideLoadingSpinner('loadingSpinner');
        }
        // Start progress updates after player is ready
        startProgressUpdate();
    } else {
        // Hide spinner after timeout as fallback
        setTimeout(() => {
            if (hideLoadingSpinner) {
                hideLoadingSpinner('loadingSpinner');
            }
        }, 10000);
    }
    
    // Setup profile selection button click handler
    setupProfileSelectionButton();
    
    // Initialize analytics tracking (event-based, sessions derived server-side)
    if (slug && analyticsTracker) {
        analyticsTracker.init(slug);
        
        // Track abandoned when page is about to unload
        window.addEventListener('beforeunload', () => {
            if (analyticsTracker) {
                analyticsTracker.trackAbandonedIfNeeded();
            }
        });
    }
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    if (!eventEmitter?.on) {
        console.error('[Player] Event emitter not available - cannot set up event listeners');
        return;
    }
    
    // Video state changes
    eventEmitter.on('video:statechange', (data) => {
        updateControlsState(data.state);
        
        // Track analytics events
        if (analyticsTracker && slug) {
            const videoPlayer = getVideoPlayer();
            const player = videoPlayer?.getPlayer?.();
            const currentVideoId = appState?.get?.('currentVideoId');
            const currentPlaylistId = appState?.get?.('currentPlaylistId');
            
            // Get database video ID
            let videoDbId = null;
            if (playlistId && playlistVideos && playlistVideos.length > 0 && currentIndex >= 0 && currentIndex < playlistVideos.length) {
                videoDbId = playlistVideos[currentIndex]?.id || null;
            } else {
                videoDbId = initialVideoDbId;
            }
            
            if (player && currentVideoId && videoDbId) {
                const YT = window.YT;
                if (YT) {
                    const state = data.state;
                    const currentTime = player.getCurrentTime?.() || 0;
                    const duration = player.getDuration?.() || 0;
                    
                    if (state === YT.PlayerState.PLAYING) {
                        analyticsTracker.trackVideoResumed(videoDbId, Math.floor(currentTime), Math.floor(duration), currentPlaylistId ? parseInt(currentPlaylistId, 10) : null);
                        // Start position tracking
                        analyticsTracker.startPositionTracking(
                            videoDbId,
                            () => player.getCurrentTime?.() || 0,
                            () => player.getDuration?.() || 0,
                            currentPlaylistId ? parseInt(currentPlaylistId, 10) : null
                        );
                    } else if (state === YT.PlayerState.PAUSED || state === YT.PlayerState.CUED) {
                        analyticsTracker.trackVideoPaused(videoDbId, Math.floor(currentTime), Math.floor(duration), currentPlaylistId ? parseInt(currentPlaylistId, 10) : null);
                        analyticsTracker.stopPositionTracking();
                    } else if (state === YT.PlayerState.ENDED) {
                        // Only track completion once - the video:ended event will also fire
                        // but we track it here to ensure we capture the duration correctly
                        // The analytics tracker will prevent duplicate completions
                        analyticsTracker.trackVideoCompleted(videoDbId, Math.floor(duration), currentPlaylistId ? parseInt(currentPlaylistId, 10) : null);
                        analyticsTracker.stopPositionTracking();
                    }
                }
            }
        }
    });

    // Video ended
    // NOTE: playlist.js already handles video:ended for playlists
    // This handler is only for single videos on the dedicated player page
    // The unified gallery/player page (galleries/index.js) also has a handler
    // but it defers to playlist.js for playlist videos
    eventEmitter.on('video:ended', () => {
        const playlist = getPlaylist();
        // Check if we're in a playlist first
        if (playlistId && playlistVideos && playlistVideos.length > 0 && playlist?.handleVideoEnded) {
            // Let playlist module handle it (it will navigate if last video)
            playlist.handleVideoEnded();
        } else {
            // Single video - navigate back to gallery immediately
            setTimeout(() => {
                navigateToGallery();
            }, 1000); // Increased delay to ensure video state is fully updated
        }
    });

    // Single video ended (from playlist module)
    eventEmitter.on('video:ended-single', () => {
        navigateToGallery();
    });

    // Playlist ended
    eventEmitter.on('playlist:ended', () => {
        const fullscreen = getFullscreen();
        if (fullscreen?.exitBeforeGallerySwitch) {
            fullscreen.exitBeforeGallerySwitch().then(() => {
                navigateToGallery();
            }).catch(() => {
                navigateToGallery();
            });
        } else {
            navigateToGallery();
        }
    });

    // Playlist URL update
    eventEmitter.on('playlist:update-url', (data) => {
        if (data.index !== undefined) {
            updatePlaylistUrl(data.index);
        }
    });

    // Time updates
    eventEmitter.on('video:timeupdate', (data) => {
        updateProgress(data.currentTime, data.duration);
    });
}

/**
 * Setup controls
 */
function setupControls() {
    const videoPlayer = getVideoPlayer();
    const fullscreen = getFullscreen();
    
    // Note: Play/pause button, progress bar, and all controls are now handled by the controls module
    // The controls module will attach handlers when it initializes
    // We keep progress bar drag setup here as it's specific to this page's implementation
    if (progressBar) {
        // Progress bar drag (keep this as it's page-specific)
        setupProgressDrag();
    }

    // Fullscreen button - let fullscreen module handle it
    // The fullscreen module will attach the handler and hide the button if not supported
    if (fullscreen?.attachButtonHandler) {
        // Attach handler via fullscreen module
        fullscreen.attachButtonHandler();
        
        // Also set up a wrapper to show control bar when fullscreen toggles
        // Use controls module if available, otherwise fallback to local functions
        if (eventEmitter?.on) {
            eventEmitter.on('fullscreen:change', () => {
                const controls = window.Traktor?.Modules?.controls;
                if (controls?.showControlBar) {
                    controls.showControlBar();
                    if (controls?.scheduleAutoHideInternal) {
                        controls.scheduleAutoHideInternal();
                    }
                } else {
                    // Fallback to local functions
                    showControlBar();
                    scheduleAutoHide();
                }
            });
        }
    } else if (fullscreenBtn && (!fullscreen || !fullscreen.getIsSupported?.())) {
        // Hide button if fullscreen is not supported or module not available
        fullscreenBtn.style.display = 'none';
    }

    // Note: All touch/click interactions are now handled by the controls module
    // The controls module will handle:
    // - Video tap/click (with proper visibility checking)
    // - Control bar interactions
    // - Auto-hide scheduling
    // We only keep the double-click fullscreen handler here as a fallback
    
    // Double-click fullscreen (fallback - controls module may also handle this)
    if (clickBlocker && fullscreen?.toggle) {
        // Check if handler already attached
        if (!clickBlocker.hasAttribute('data-dblclick-handler-attached')) {
            clickBlocker.setAttribute('data-dblclick-handler-attached', 'true');
            clickBlocker.addEventListener('dblclick', (e) => {
                e.preventDefault();
                e.stopPropagation();
                fullscreen.toggle();
                // Let controls module handle showing control bar and scheduling auto-hide
                const controls = window.Traktor?.Modules?.controls;
                if (controls?.showControlBar) {
                    controls.showControlBar();
                } else {
                    showControlBar();
                    scheduleAutoHide();
                }
            });
        }
    }

    // Progress updates will be started after player is ready (in init function)
}

/**
 * Setup progress bar drag
 */
function setupProgressDrag() {
    if (!progressBar) return;

    const startDrag = (e) => {
        isDragging = true;
        showControlBar();
        clearAutoHide();

        // Handle both mouse and touch
        let clientX;
        if (e.clientX !== undefined) {
            clientX = e.clientX;
        } else if (e.touches?.[0]) {
            clientX = e.touches[0].clientX;
        } else if (e.changedTouches?.[0]) {
            clientX = e.changedTouches[0].clientX;
        }
        if (clientX !== undefined) {
            handleProgressSeek(e);
        }
    };

    const drag = (e) => {
        if (!isDragging) return;
        showControlBar();
        clearAutoHide();
        requestAnimationFrame(() => {
            handleProgressSeek(e);
        });
    };

    const endDrag = () => {
        isDragging = false;
        if (appState?.get && !appState.get('isVideoPaused')) {
            scheduleAutoHide();
        }
    };

    // Mouse events
    progressBar.addEventListener('mousedown', startDrag);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', endDrag);

    // Touch events
    progressBar.addEventListener('touchstart', (e) => {
        e.preventDefault();
        const touch = e.touches?.[0] || e.changedTouches?.[0];
        if (touch) {
            startDrag(touch);
        }
    }, { passive: false });

    document.addEventListener('touchmove', (e) => {
        if (!isDragging) return;
        e.preventDefault();
        const touch = e.touches?.[0] || e.changedTouches?.[0];
        if (touch) {
            drag(touch);
        }
    }, { passive: false });

    document.addEventListener('touchend', endDrag);
    document.addEventListener('touchcancel', endDrag);
}

/**
 * Handle progress bar click or seek
 */
function handleProgressClick(e) {
    if (isDragging) return;
    handleProgressSeek(e);
}

/**
 * Handle progress seek
 * @param {Event|TouchEvent} e - Mouse or touch event
 */
function handleProgressSeek(e) {
    const videoPlayer = getVideoPlayer();
    const player = videoPlayer?.getPlayer?.();
    if (!player || !progressBar) return;

    try {
        const rect = progressBar.getBoundingClientRect();
        let clientX;
        if (e.clientX !== undefined) {
            clientX = e.clientX;
        } else if (e.touches?.[0]) {
            clientX = e.touches[0].clientX;
        } else if (e.changedTouches?.[0]) {
            clientX = e.changedTouches[0].clientX;
        }
        if (clientX === undefined) return;

        const clickX = clientX - rect.left;
        const percent = Math.max(0, Math.min(1, clickX / rect.width));

        const duration = player.getDuration();
        if (duration) {
            const newTime = duration * percent;

            // Update UI immediately
            if (progressFill) {
                progressFill.style.width = `${percent * 100}%`;
            }

            if (currentTimeEl && formatTime) {
                currentTimeEl.textContent = formatTime(newTime);
            }

            // Seek video
            if (videoPlayer?.seekTo) {
                videoPlayer.seekTo(newTime);
            }
        }
    } catch (error) {
        if (errorHandler?.handle) {
            errorHandler.handle(error, {
                showToast: false,
                logToConsole: true,
                context: { action: 'handleProgressSeek' }
            });
        }
    }
}

/**
 * Update progress bar and time display
 */
function updateProgress(currentTime, duration) {
    if (isDragging || !duration) return;

    try {
        const percent = (currentTime / duration) * 100;

        if (progressFill) {
            progressFill.style.width = `${percent}%`;
        }

        if (currentTimeEl && formatTime) {
            currentTimeEl.textContent = formatTime(currentTime);
        }

        if (durationEl && durationEl.textContent === '0:00' && formatTime) {
            durationEl.textContent = formatTime(duration);
        }
    } catch (error) {
        if (errorHandler?.handle) {
            errorHandler.handle(error, {
                showToast: false,
                logToConsole: true,
                context: { action: 'updateProgress' }
            });
        }
    }
}

/**
 * Start progress update interval
 */
function startProgressUpdate() {
    if (progressUpdateInterval) {
        clearInterval(progressUpdateInterval);
    }

    progressUpdateInterval = setInterval(() => {
        const videoPlayer = getVideoPlayer();
        if (!videoPlayer?.isReady?.()) {
            stopProgressUpdate();
            return;
        }

        if (isDragging) return;

        try {
            const player = videoPlayer.getPlayer?.();
            if (!player) return;

            const currentTime = player.getCurrentTime();
            const duration = player.getDuration();

            if (duration) {
                updateProgress(currentTime, duration);
                if (eventEmitter?.emit) {
                    eventEmitter.emit('video:timeupdate', { currentTime: currentTime, duration: duration });
                }
            }
        } catch (error) {
            if (errorHandler?.handle) {
                errorHandler.handle(error, {
                    showToast: false,
                    logToConsole: true,
                    context: { action: 'progressUpdateInterval' }
                });
            }
        }
    }, TimingConstants?.PROGRESS_UPDATE_INTERVAL || 100);
}

/**
 * Stop progress update interval
 */
function stopProgressUpdate() {
    if (progressUpdateInterval) {
        clearInterval(progressUpdateInterval);
        progressUpdateInterval = null;
    }
}

/**
 * Update controls state based on video state
 */
function updateControlsState(state) {
    const YT = window.YT;
    if (!YT) return;

    const isPlaying = state === YT.PlayerState.PLAYING;
    const isPaused = state === YT.PlayerState.PAUSED || state === YT.PlayerState.CUED;

    // Update play/pause button
    if (playIcon && pauseIcon) {
        if (isPlaying) {
            playIcon.classList.add('d-none');
            pauseIcon.classList.remove('d-none');
        } else {
            playIcon.classList.remove('d-none');
            pauseIcon.classList.add('d-none');
        }
    }

    // Update pause overlay and cat GIF
    if (isPaused) {
        showPauseOverlay();
    } else {
        hidePauseOverlay();
    }

    // Auto-hide controls when playing - controls module handles this via updateState()
    // We only handle pause overlay here, controls module handles visibility
    // Note: The controls module's updateState() method will be called via the 'video:statechange' event
    // and it will handle showing/hiding the control bar and scheduling auto-hide
    // We keep this function for pause overlay only, but don't duplicate auto-hide logic
}

/**
 * Show pause overlay with cat GIF
 */
function showPauseOverlay() {
    if (videoContainer) {
        videoContainer.classList.add('paused-overlay');
    }

    if (videoOverlayEffect) {
        videoOverlayEffect.classList.add('active');
    }

    if (catGifContainer && catGifs.length > 0) {
        // Select random cat GIF
        const randomIndex = Math.floor(Math.random() * catGifs.length);
        const catGifFile = catGifs[randomIndex];

        if (catGifImg) {
            catGifImg.src = `/assets/cats/${catGifFile}`;
            catGifContainer.classList.remove('d-none', 'hide');
            catGifContainer.classList.add('show');
        }
    }
}

/**
 * Hide pause overlay
 */
function hidePauseOverlay() {
    if (videoContainer) {
        videoContainer.classList.remove('paused-overlay');
    }

    if (videoOverlayEffect) {
        videoOverlayEffect.classList.remove('active');
    }

    if (catGifContainer) {
        catGifContainer.classList.remove('show');
        catGifContainer.classList.add('hide');
        // Delay hiding to allow fade-out animation
        setTimeout(() => {
            catGifContainer.classList.add('d-none');
        }, 500); // Keep as-is (animation timing, not a constant)
    }
}

/**
 * Size video to fit viewport - simple calculation
 */
function sizeVideoToFit() {
    const player = document.getElementById('player');
    if (!player) return;
    
    const vw = window.innerWidth || document.documentElement.clientWidth;
    const vh = window.innerHeight || document.documentElement.clientHeight;
    
    // Calculate dimensions for both approaches
    const widthBased = { w: vw, h: vw * 9 / 16 };
    const heightBased = { w: vh * 16 / 9, h: vh };
    
    // Use whichever fits within viewport
    const useWidthBased = widthBased.h <= vh;
    const dims = useWidthBased ? widthBased : heightBased;
    
    // Ensure we don't exceed viewport
    dims.w = Math.min(dims.w, vw);
    dims.h = Math.min(dims.h, vh);
    
    // Set explicit dimensions
    player.style.width = `${dims.w}px`;
    player.style.height = `${dims.h}px`;
    player.style.paddingBottom = '0';
    player.style.maxWidth = `${vw}px`;
    player.style.maxHeight = `${vh}px`;
}

/**
 * Show control bar and navbar
 * Uses cached navbar element for better performance
 * Always refresh element reference to ensure it's found
 */
function showControlBar() {
    // Refresh control bar reference if needed
    if (!customControlBar) {
        customControlBar = document.getElementById('customControlBar');
    }
    if (customControlBar) {
        customControlBar.classList.remove('hidden');
        // Explicitly ensure visibility
        // Check computed styles to see if CSS is applying correctly
        const computedStyle = window.getComputedStyle?.(customControlBar);
        if (computedStyle) {
            // If CSS isn't applying, set inline styles as fallback
            if (computedStyle.display === 'none' || computedStyle.visibility === 'hidden' || parseFloat(computedStyle.opacity) === 0 || computedStyle.bottom !== '0px') {
                customControlBar.style.setProperty('display', 'flex', 'important');
                customControlBar.style.setProperty('opacity', '1', 'important');
                customControlBar.style.setProperty('visibility', 'visible', 'important');
                customControlBar.style.setProperty('position', 'fixed', 'important');
                customControlBar.style.setProperty('bottom', '0', 'important');
                customControlBar.style.setProperty('top', 'auto', 'important');
                customControlBar.style.setProperty('left', '0', 'important');
                customControlBar.style.setProperty('right', '0', 'important');
                customControlBar.style.setProperty('z-index', '1002', 'important');
            }
        }
    }
    // Show navbar (refresh reference if needed)
    if (!navbarElement) {
        navbarElement = document.querySelector('.top-navbar.player-view-mode');
    }
    if (navbarElement) {
        navbarElement.classList.remove('hidden');
    }
}

/**
 * Hide control bar and navbar
 * Uses cached navbar element for better performance
 */
function hideControlBar() {
    if (customControlBar) {
        customControlBar.classList.add('hidden');
    }
    // Hide navbar (use cached element)
    if (navbarElement) {
        navbarElement.classList.add('hidden');
    }
}

/**
 * Schedule auto-hide of control bar
 */
function scheduleAutoHide() {
    clearAutoHide();
    // Don't auto-hide if control bar isn't visible
    // This prevents hiding it before it's even shown
    if (!customControlBar) {
        customControlBar = document.getElementById('customControlBar');
    }
    if (!customControlBar || customControlBar.classList.contains('hidden')) {
        // Control bar isn't visible, don't schedule hide
        return;
    }
    autoHideTimeout = setTimeout(() => {
        if (appState?.get && !appState.get('isVideoPaused')) {
            hideControlBar();
        }
    }, TimingConstants?.AUTO_HIDE_DELAY || 3000);
}

/**
 * Clear auto-hide timeout
 */
function clearAutoHide() {
    if (autoHideTimeout) {
        clearTimeout(autoHideTimeout);
        autoHideTimeout = null;
    }
}

/**
 * Setup keyboard shortcuts
 */
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Ignore if typing in input/textarea
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }

        const videoPlayer = getVideoPlayer();
        const fullscreen = getFullscreen();
    
        // Spacebar - play/pause
        if (e.code === 'Space') {
            e.preventDefault();
            if (videoPlayer?.togglePlayPause) {
                videoPlayer.togglePlayPause();
            }
            showControlBar();
            scheduleAutoHide();
        }

        // F - fullscreen (only if supported)
        if (e.code === 'KeyF') {
            e.preventDefault();
            if (fullscreen?.toggle && fullscreen.getIsSupported?.()) {
                fullscreen.toggle();
            }
            showControlBar();
            scheduleAutoHide();
        }

        // Arrow keys - seek (only in single video mode, not playlist)
        if (!playlistId) {
            if (e.code === 'ArrowLeft') {
                e.preventDefault();
                seekRelative(-10);
            } else if (e.code === 'ArrowRight') {
                e.preventDefault();
                seekRelative(10);
            }
        }
    });
}

/**
 * Seek relative to current time
 * @param {number} seconds - Number of seconds to seek (positive or negative)
 */
function seekRelative(seconds) {
    const videoPlayer = getVideoPlayer();
    const player = videoPlayer?.getPlayer?.();
    if (!player) return;

    try {
        const currentTime = player.getCurrentTime();
        const duration = player.getDuration();
        const newTime = Math.max(0, Math.min(duration, currentTime + seconds));
        if (videoPlayer?.seekTo) {
            videoPlayer.seekTo(newTime);
        }
        showControlBar();
        scheduleAutoHide();
    } catch (error) {
        if (errorHandler?.handle) {
            errorHandler.handle(error, {
                showToast: false,
                logToConsole: true,
                context: { action: 'seekRelative', seconds: seconds }
            });
        }
    }
}

/**
 * Setup fullscreen
 */
function setupFullscreen() {
    if (!eventEmitter?.on) return;
    
    const fullscreen = getFullscreen();
    
    // Update fullscreen button icon on change
    eventEmitter.on('fullscreen:change', (data) => {
        updateFullscreenButton(data.isFullscreen);
    });

    // Setup double-click/tap for fullscreen (only if supported)
    if (clickBlocker && fullscreen?.toggle && fullscreen.getIsSupported?.()) {
        let lastClickTime = 0;

        // Double-click (mouse)
        clickBlocker.addEventListener('dblclick', (e) => {
            e.preventDefault();
            if (fullscreen?.toggle) {
                fullscreen.toggle();
                showControlBar();
                scheduleAutoHide();
            }
        });

        // Double-tap (touch)
        let touchStartTime = 0;
        clickBlocker.addEventListener('touchstart', () => {
            touchStartTime = Date.now();
        });

        clickBlocker.addEventListener('touchend', (e) => {
            const touchDuration = Date.now() - touchStartTime;

            if (TimingConstants && touchDuration < TimingConstants.TOUCH_DURATION_THRESHOLD) {
                const currentTime = Date.now();
                const timeSinceLastClick = currentTime - lastClickTime;

                if (TimingConstants && timeSinceLastClick < TimingConstants.DOUBLE_CLICK_DELAY) {
                    e.preventDefault();
                    if (fullscreen?.toggle) {
                        fullscreen.toggle();
                        showControlBar();
                        scheduleAutoHide();
                    }
                    lastClickTime = 0;
                } else {
                    lastClickTime = currentTime;
                }
            }
        });
    }
}

/**
 * Update fullscreen button icon
 */
function updateFullscreenButton(isFullscreen) {
    if (!fullscreenBtn) return;

    if (isFullscreen) {
        fullscreenBtn.innerHTML = '<i class="bi bi-fullscreen-exit fs-4"></i>';
    } else {
        fullscreenBtn.innerHTML = '<i class="bi bi-arrows-fullscreen fs-4"></i>';
    }
}

/**
 * Play video
 */
function playVideo(videoId) {
    const videoPlayer = getVideoPlayer();
    if (!videoId || !videoPlayer?.loadVideo) {
        // If no video ID provided, try to get it from playlist if available
        if (!videoId && playlistId && playlistVideos && playlistVideos.length > 0 && currentIndex >= 0 && currentIndex < playlistVideos.length) {
            const playlistVideo = playlistVideos[currentIndex];
            if (playlistVideo && playlistVideo.video_id) {
                videoId = playlistVideo.video_id;
            }
        }
        
        // If still no video ID, log error and return
        if (!videoId) {
            console.error('[Player] Cannot play video - no video ID available', {
                initialVideoId: initialVideoId,
                playlistId: playlistId,
                playlistVideos: playlistVideos,
                currentIndex: currentIndex
            });
            return;
        }
    }
    
    // Ensure controls are visible before attempting to play (class toggling only)
    if (customControlBar) {
        customControlBar.classList.remove('hidden');
    }
    
    // Try autoplay (may be blocked by browser)
    // Pass true for autoplay - the loadVideo method will attempt to play
    videoPlayer.loadVideo(videoId, true);
    
    // Track video started
    if (analyticsTracker && slug) {
        const currentPlaylistId = appState?.get?.('currentPlaylistId');
        // Get database video ID from playlist videos or use initial video DB ID
        let videoDbId = null;
        if (playlistId && playlistVideos && playlistVideos.length > 0 && currentIndex >= 0 && currentIndex < playlistVideos.length) {
            videoDbId = playlistVideos[currentIndex]?.id || null;
        } else {
            videoDbId = initialVideoDbId;
        }
        
        // Get video duration from appState or wait for player to be ready
        setTimeout(() => {
            const player = videoPlayer?.getPlayer?.();
            const duration = player?.getDuration?.() || null;
            if (videoDbId) {
                analyticsTracker.trackVideoStarted(
                    videoDbId,
                    currentPlaylistId ? parseInt(currentPlaylistId, 10) : null,
                    duration ? Math.floor(duration) : null
                );
            }
        }, 1000);
    }
    
    // Also try to play immediately after loading (for AJAX-loaded views with user gesture)
    // This ensures autoplay works when loaded via view manager
    setTimeout(() => {
        if (videoPlayer?.isReady?.() && videoPlayer.play) {
            try {
                videoPlayer.play();
            } catch (e) {
                // Autoplay blocked - user will need to click play button
            }
        }
    }, 600); // Wait for video to load (slightly longer than loadVideo timeout)
}

/**
 * Navigate to gallery
 */
async function navigateToGallery() {
    if (!slug) {
        console.error('[Player] Cannot navigate - slug is missing');
        return;
    }
    
    // Cleanup analytics tracking before navigating
    if (analyticsTracker) {
        analyticsTracker.cleanup();
    }
    
    // Navigate to gallery page
    const queryParams = {};
    if (channelId && channelId !== 'all') {
        queryParams.channel = channelId;
    }

    const queryString = buildQueryString?.(queryParams) || '';
    const url = `/${slug}/gallery${queryString ? `?${queryString}` : ''}`;
    window.location.href = url;
}

/**
 * Update playlist URL via History API
 */
function updatePlaylistUrl(index) {
    const queryParams = {
        channel: channelId || 'all',
        index: index
    };

    const queryString = buildQueryString?.(queryParams) || '';
    const url = `/${slug}/player/playlist/${playlistId}${queryString ? `?${queryString}` : ''}`;
    window.history.replaceState({}, '', url);
}

/**
 * Setup profile selection button click handler
 */
function setupProfileSelectionButton() {
    const profileSelectionBtn = document.getElementById('profileSelectionBtn');
    if (profileSelectionBtn && !profileSelectionBtn.hasAttribute('data-handler-attached')) {
        profileSelectionBtn.setAttribute('data-handler-attached', 'true');
        profileSelectionBtn.addEventListener('click', () => {
            // Clear viewing session and redirect to home (will redirect to welcome if no device registered)
            window.location.href = '/';
        });
    }
}

/**
 * Setup playlist navigation buttons
 */
function setupPlaylistButtons() {
    const playlist = getPlaylist();
    
    if (prevVideoBtn && playlist?.prev) {
        prevVideoBtn.addEventListener('click', () => {
            playlist.prev();
            showControlBar();
            scheduleAutoHide();
        });
    }

    if (nextVideoBtn && playlist?.next) {
        nextVideoBtn.addEventListener('click', () => {
            playlist.next();
            showControlBar();
            scheduleAutoHide();
        });
    }

    // Setup return to gallery button handler
    const fullscreen = getFullscreen();
    if (returnToGalleryBtn && !returnToGalleryBtn.hasAttribute('data-handler-attached')) {
        returnToGalleryBtn.setAttribute('data-handler-attached', 'true');
        
        const handleGalleryNavigation = (e) => {
            if (e) {
                e.stopPropagation();
                e.preventDefault();
            }
            
            // Exit fullscreen if in fullscreen mode
            if (fullscreen?.exitBeforeGallerySwitch) {
                fullscreen.exitBeforeGallerySwitch().then(() => {
                    navigateToGallery();
                }).catch(() => {
                    navigateToGallery();
                });
            } else {
                // Direct navigation if not in fullscreen
                navigateToGallery();
            }
        };
        
        returnToGalleryBtn.addEventListener('click', handleGalleryNavigation);
        
        // Add touch handler
        returnToGalleryBtn.addEventListener('touchend', handleGalleryNavigation, { passive: false });
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        init();
        setupPlaylistButtons();
    });
} else {
    init();
    setupPlaylistButtons();
}

// Also listen for AJAX-loaded player view (when loaded via view manager)
if (eventEmitter?.on) {
    eventEmitter.on('view:player-loaded', () => {
        // Re-query DOM elements since they were just loaded via AJAX
        playerView = document.querySelector('.player-view');
        videoContainer = document.getElementById('videoContainer');
        customControlBar = document.getElementById('customControlBar');
        playPauseBtn = document.getElementById('customPlayPause');
        playIcon = playPauseBtn?.querySelector('.play-icon');
        pauseIcon = playPauseBtn?.querySelector('.pause-icon');
        progressBar = document.getElementById('customProgressBar');
        progressFill = document.getElementById('customProgressFill');
        currentTimeEl = document.getElementById('currentTime');
        durationEl = document.getElementById('duration');
        fullscreenBtn = document.getElementById('customFullscreen');
        clickBlocker = document.querySelector('.custom-click-blocker');
        catGifContainer = document.querySelector('.cat-gif-container');
        catGifImg = document.getElementById('catGif');
        videoOverlayEffect = document.querySelector('.video-overlay-effect');
        returnToGalleryBtn = document.getElementById('returnToGalleryBtn');
        prevVideoBtn = document.getElementById('prevVideoBtn');
        nextVideoBtn = document.getElementById('nextVideoBtn');
        
        // Re-read data from script tag
        slug = getScriptData?.('data-slug') || null;
        initialVideoId = getScriptData?.('data-video-id') || null;
        channelId = getScriptData?.('data-channel-id') || null;
        playlistId = getScriptData?.('data-playlist-id') || null;
        playlistVideos = getScriptDataJson?.('data-playlist-videos', []) || [];
        currentIndex = parseIntSafe?.(getScriptData?.('data-current-index'), 0) || 0;
        catGifs = getScriptDataJson?.('data-cat-gifs', []) || [];
        
        // Get the video ID to play - prefer playlist video if in playlist mode
        const videoIdToPlay = (() => {
            // If we have a playlist with videos, get video ID from playlist
            if (playlistId && playlistVideos && playlistVideos.length > 0 && currentIndex >= 0 && currentIndex < playlistVideos.length) {
                const playlistVideo = playlistVideos[currentIndex];
                if (playlistVideo && playlistVideo.video_id) {
                    return playlistVideo.video_id;
                }
            }
            // Otherwise use initialVideoId
            return initialVideoId;
        })();
        
        // If we have a video ID and player is ready, play it
        if (videoIdToPlay) {
            const videoPlayer = getVideoPlayer();
            if (videoPlayer?.isReady?.()) {
                playVideo(videoIdToPlay);
            } else if (eventEmitter?.on) {
                // Wait for player to be ready
                eventEmitter.once('player:ready', () => {
                    playVideo(videoIdToPlay);
                });
            }
        }
        
        // Reinitialize player page
        init();
        setupPlaylistButtons();
    });
}
