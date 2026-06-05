/**
 * Controls Module
 * Handles custom video player controls, progress bar, auto-hide, and cat GIF display
 */

import { appState } from '../core/state.js';
import { eventEmitter } from '../core/events.js';
import { TimingConstants } from '../core/constants.js';
import { formatTime, getTranslation, isFullscreenSupported } from '../core/utils.js';

// Import other modules dynamically to avoid circular dependencies
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

export class Controls {
    constructor() {
        this.progressUpdateInterval = null;
        this.catGifContainer = null;
        this.currentCatGif = null;
        this.videoOverlay = null;
        this.controlBarAutoHideTimeout = null;
        this.isDragging = false; // Track if progress bar is being dragged
        this.clickBlocker = null;
        this.catGifImage = null;
        this.playerElement = document.getElementById('videoContainer');
        this.init();
    }
    
    init() {
        // All elements are in Blade template - just find and set up handlers
        this.setupElements();
        
        // Initialize when player is ready
        if (eventEmitter?.on) {
            eventEmitter.on('player:ready', () => {
                this.setupElements();
            });
            
            eventEmitter.on('view:player-shown', () => {
                setTimeout(() => {
                    this.setupElements();
                }, 200);
            });
            
            // If player is already ready, setup immediately
            if (appState?.get?.('playerReady')) {
                setTimeout(() => {
                    this.setupElements();
                }, 100);
            }
            
            // Listen for video state changes
            eventEmitter.on('video:statechange', (data) => {
                this.updateState(data.state);
            });
        }
    }
    
    // Setup elements from Blade template
    setupElements() {
        // Find elements (all should exist in Blade template)
        this.catGifContainer = document.querySelector('.overlay-layer .cat-gif-container');
        this.videoOverlay = document.querySelector('.overlay-layer .video-overlay-effect');
        this.clickBlocker = document.querySelector('.click-blocker-layer');
        
        // Find cat GIF image
        if (this.catGifContainer) {
            this.catGifImage = this.catGifContainer.querySelector('img');
            if (!this.catGifImage) {
                this.catGifImage = document.createElement('img');
                this.catGifImage.alt = getTranslation?.('common.cat_gif', 'Cat GIF') || 'Cat GIF';
                this.catGifImage.loading = 'eager';
                this.catGifContainer.appendChild(this.catGifImage);
            }
        }
        
        // Setup event handlers
        this.setupClickBlocker();
        this.setupControlBar();
        this.setupAutoHide();
        this.setupHoverHandlers();
        
        // Start progress update if video is playing
        const videoPlayer = getVideoPlayer();
        if (videoPlayer?.isReady?.() && appState?.get && !appState.get('isVideoPaused')) {
            const playerState = videoPlayer.getPlayerState?.();
            if (playerState === YT.PlayerState.PLAYING) {
                this.startProgressUpdate();
            }
        }
    }
    
    // Setup control bar buttons and handlers
    setupControlBar() {
        const controlBar = document.getElementById('customControlBar');
        if (!controlBar || controlBar.hasAttribute('data-handlers-attached')) return;
        
        controlBar.setAttribute('data-handlers-attached', 'true');
        
        // Prevent control bar events from propagating to click blocker
        controlBar.addEventListener('touchstart', (e) => e.stopPropagation(), { passive: true });
        controlBar.addEventListener('touchend', (e) => {
            e.stopPropagation();
            this.showControlBar();
            if (appState?.get && !appState.get('isVideoPaused')) {
                this.scheduleAutoHide();
            }
        }, { passive: false });
        controlBar.addEventListener('click', (e) => e.stopPropagation());
        
        // Setup play/pause button
        const playPauseBtn = document.getElementById('customPlayPause');
        const videoPlayer = getVideoPlayer();
        if (playPauseBtn && !playPauseBtn.hasAttribute('data-handler-attached')) {
            playPauseBtn.setAttribute('data-handler-attached', 'true');
            
            playPauseBtn.addEventListener('touchstart', (e) => e.stopPropagation(), { passive: true });
            
            const handlePlayPause = (e) => {
                e.stopPropagation();
                e.preventDefault();
                if (videoPlayer?.togglePlayPause) {
                    const currentState = videoPlayer.getPlayerState?.();
                    const willBePlaying = currentState !== YT.PlayerState.PLAYING;
                    videoPlayer.togglePlayPause();
                    if (willBePlaying) {
                        setTimeout(() => {
                            if (videoPlayer.getPlayerState?.() === YT.PlayerState.PLAYING) {
                                this.scheduleAutoHide();
                            }
                        }, 100);
                    }
                }
            };
            
            playPauseBtn.addEventListener('click', handlePlayPause);
            playPauseBtn.addEventListener('touchend', handlePlayPause, { passive: false });
        }
        
        // Setup progress bar
        const progressBar = document.getElementById('customProgressBar');
        if (progressBar && !progressBar.hasAttribute('data-handler-attached')) {
            progressBar.setAttribute('data-handler-attached', 'true');
            progressBar.addEventListener('touchstart', (e) => {
                if (!this.isDragging) e.stopPropagation();
            }, { passive: false });
            progressBar.addEventListener('click', (e) => {
                e.stopPropagation();
                this.handleProgressClick(e);
            });
            this.setupProgressBarDrag(progressBar);
        }
        
        // Setup fullscreen button
        const fullscreenBtn = document.getElementById('customFullscreen');
        if (fullscreenBtn) {
            const isFullscreenSupportedValue = isFullscreenSupported?.() ?? true;
            if (!isFullscreenSupportedValue) {
                fullscreenBtn.style.display = 'none';
            } else if (!fullscreenBtn.hasAttribute('data-handler-attached')) {
                fullscreenBtn.setAttribute('data-handler-attached', 'true');
                fullscreenBtn.addEventListener('touchstart', (e) => e.stopPropagation(), { passive: true });
                if (eventEmitter?.emit) {
                    eventEmitter.emit('controls:ready', { fullscreenBtn: fullscreenBtn });
                }
            }
        }
    }
    
    
    // Show controls (not used in simplified structure, but kept for compatibility)
    show() {
        this.startProgressUpdate();
        this.showControlBar();
    }
    
    // Hide controls (not used in simplified structure, but kept for compatibility)
    hide() {
        this.stopProgressUpdate();
    }
    
    // Show control bar and navbar - simplified
    showControlBar() {
        const controlBar = document.querySelector('.custom-control-bar') || document.getElementById('customControlBar');
        if (controlBar) {
            this.clearAutoHide();
            controlBar.classList.remove('hidden');
            const navbarInstance = getNavbar();
            if (navbarInstance?.show) {
                navbarInstance.show();
            }
        }
    }
    
    // Hide control bar and navbar - simplified
    hideControlBar() {
        // Don't hide if dragging or video is paused
        if (this.isDragging || (appState?.get?.('isVideoPaused'))) return;
        
        const controlBar = document.querySelector('.custom-control-bar') || document.getElementById('customControlBar');
        if (controlBar) {
            controlBar.classList.add('hidden');
            const navbarInstance = getNavbar();
            if (navbarInstance?.hide) {
                navbarInstance.hide();
            }
        }
    }
    
    // Auto-hide logic
    clearAutoHide() {
        if (this.controlBarAutoHideTimeout) {
            clearTimeout(this.controlBarAutoHideTimeout);
            this.controlBarAutoHideTimeout = null;
        }
    }
    
    scheduleAutoHide() {
        this.clearAutoHide();
        // Don't schedule auto-hide if we're dragging or video is paused
        if (this.isDragging || (appState?.get?.('isVideoPaused'))) return;
        
        // Get auto-hide delay from constants (default to 3000ms if not available)
        const autoHideDelay = TimingConstants?.AUTO_HIDE_DELAY || 3000;
        
        this.controlBarAutoHideTimeout = setTimeout(() => {
            // Double-check state before hiding
            if (!this.isDragging && appState?.get && !appState.get('isVideoPaused')) {
                this.hideControlBar();
            }
        }, autoHideDelay);
    }
    
    // Public method for external callers (fullscreen module)
    scheduleAutoHideInternal() {
        this.scheduleAutoHide();
    }
    
    setupAutoHide() {
        if (!this.playerElement) return;
        
        // Simple mousemove handler - show controls and schedule auto-hide
        // Throttle to prevent constant timer resets
        let lastMousemoveTime = 0;
        this.playerElement.addEventListener('mousemove', () => {
            // Don't trigger auto-hide if we're dragging the progress bar
            if (this.isDragging) return;
            
            // Throttle mousemove - only process every 200ms
            const now = Date.now();
            if (now - lastMousemoveTime < 200) return;
            lastMousemoveTime = now;
            
            this.showControlBar();
            this.scheduleAutoHide();
        });
        
        // Touch events for mobile - show controls and schedule auto-hide
        let lastTouchTime = 0;
        this.playerElement.addEventListener('touchstart', () => {
            // Don't trigger auto-hide if we're dragging the progress bar
            if (this.isDragging) return;
            
            // Throttle touch - only process every 200ms
            const now = Date.now();
            if (now - lastTouchTime < 200) return;
            lastTouchTime = now;
            
            // Only show controls and schedule auto-hide if video is playing
            // (If paused, controls should stay visible)
            if (appState?.get && !appState.get('isVideoPaused')) {
                this.showControlBar();
                this.scheduleAutoHide();
            }
        }, { passive: true });
        
        // Note: Click blocker click/touch handlers are set up in setupClickBlocker()
        
        const controlBar = document.getElementById('customControlBar');
        if (controlBar) {
            // Prevent touch events on control bar from propagating to video tap handler
            controlBar.addEventListener('touchstart', (e) => {
                e.stopPropagation();
            }, { passive: true });
            
            controlBar.addEventListener('touchend', (e) => {
                e.stopPropagation();
                // Show controls when interacting with control bar
                this.showControlBar();
                // Schedule auto-hide after interaction (if video is playing)
                if (appState?.get && !appState.get('isVideoPaused')) {
                    this.scheduleAutoHide();
                }
            }, { passive: false });
            
            controlBar.addEventListener('mouseenter', () => {
                this.showControlBar();
                this.clearAutoHide();
            });
            
            controlBar.addEventListener('mouseleave', () => {
                // Don't schedule auto-hide if we're dragging or if video is paused
                if (this.isDragging || (appState?.get?.('isVideoPaused'))) return;
                this.scheduleAutoHide();
            });
            
            // Also prevent clicks on control bar from propagating
            controlBar.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
        
        // Setup touch interaction for mobile
        this.setupClickBlocker();
    }
    
    /**
     * Check if element is part of control bar or navbar
     * @param {Element} element - Element to check
     * @returns {boolean} True if element is on control bar or navbar
     */
    isElementOnControls(element) {
        if (!element) return false;
        
        const controlBar = document.querySelector('.custom-control-bar') || document.getElementById('customControlBar');
        const navbarEl = document.querySelector('.top-navbar.player-view-mode');
        
        // Check for specific control elements by ID or class
        const controlElementIds = ['customPlayPause', 'customFullscreen', 'customProgressBar', 'customProgressFill', 'customTimeDisplay'];
        const controlElementClasses = ['custom-control-btn', 'custom-progress-container', 'custom-progress-bar', 'custom-progress-fill', 'custom-time-display'];
        
        let checkElement = element;
        while (checkElement && checkElement !== document.body) {
            // Check control bar
            if (controlBar && (checkElement === controlBar || 
                checkElement.classList?.contains('custom-control-bar') ||
                checkElement.id === 'customControlBar')) {
                return true;
            }
            // Check navbar
            if (navbarEl && (checkElement === navbarEl || 
                checkElement.classList?.contains('top-navbar') ||
                checkElement.closest?.('.top-navbar'))) {
                return true;
            }
            // Check for specific control element IDs
            if (checkElement.id && controlElementIds.includes(checkElement.id)) {
                return true;
            }
            // Check for specific control element classes
            if (checkElement.classList) {
                for (const className of controlElementClasses) {
                    if (checkElement.classList.contains(className)) {
                        return true;
                    }
                }
            }
            checkElement = checkElement.parentElement;
        }
        return false;
    }
    
    /**
     * Handle video area tap/click - simplified
     * @param {Event} e - Touch or click event
     * @param {number} clientX - X coordinate (from touch or click)
     * @param {number} clientY - Y coordinate (from touch or click)
     */
    handleVideoTap(e, clientX, clientY) {
        // Check if clicking on control surfaces - if so, don't handle
        let target = null;
        if (document.elementFromPoint) {
            target = document.elementFromPoint(clientX, clientY);
        }
        if (!target && e.target) {
            target = e.target;
        }
        
        if (target && this.isElementOnControls(target)) {
            return;
        }
        
        // Prevent event from reaching YouTube iframe - MUST be first
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation(); // Stop all other handlers including YouTube's
        
        // Simple check: is control bar hidden?
        const controlBar = document.querySelector('.custom-control-bar') || document.getElementById('customControlBar');
        const isHidden = controlBar?.classList.contains('hidden');
        
        // Debug: Log to help diagnose
        // console.log('[Controls] handleVideoTap - isHidden:', isHidden, 'controlBar:', controlBar);
        
        if (isHidden) {
            // Controls hidden - show them only (no pause)
            // CRITICAL: Do NOT call any video player methods here
            
            // Check if video was paused by YouTube's click handler - if so, resume it
            const videoPlayer = getVideoPlayer();
            if (videoPlayer?.isReady?.()) {
                const playerState = videoPlayer.getPlayerState?.();
                // If video was playing before tap but is now paused, resume it
                if (appState?.get && !appState.get('isVideoPaused') && playerState === YT.PlayerState.PAUSED) {
                    // Video was paused by YouTube - resume it immediately
                    setTimeout(() => {
                        videoPlayer.play?.();
                    }, 10);
                }
            }
            
            this.showControlBar();
            const navbarInstance = getNavbar();
            if (navbarInstance?.show) {
                navbarInstance.show();
            }
            // Schedule auto-hide if video is playing
            if (appState?.get && !appState.get('isVideoPaused')) {
                this.scheduleAutoHide();
            }
            // Return immediately - do not proceed to play/pause logic
            return;
        } else {
            // Controls visible - toggle play/pause
            const videoPlayer = getVideoPlayer();
            if (!videoPlayer?.isReady?.()) {
                return;
            }
            
            videoPlayer.togglePlayPause?.();
            
            // Schedule auto-hide if video will be playing
            const currentState = videoPlayer.getPlayerState?.();
            if (currentState !== YT.PlayerState.PLAYING) {
                setTimeout(() => {
                    if (videoPlayer.getPlayerState?.() === YT.PlayerState.PLAYING) {
                        this.scheduleAutoHide();
                    }
                }, 100);
            }
        }
    }
    
    /**
     * Setup hover handlers for showing controls on mouse hover
     */
    setupHoverHandlers() {
        if (!this.clickBlocker) return;
        
        // Remove existing hover handlers if any (to avoid duplicates)
        // We'll use a simple approach: check if handlers are already attached
        if (this.clickBlocker.hasAttribute('data-hover-handlers-attached')) return;
        
        this.clickBlocker.setAttribute('data-hover-handlers-attached', 'true');
        
        // Hover handlers - show controls on hover
        this.clickBlocker.addEventListener('mouseenter', () => {
            if (this.isDragging) return;
            this.showControlBar();
            this.clearAutoHide();
        });
        
        this.clickBlocker.addEventListener('mouseleave', () => {
            if (this.isDragging || (appState?.get?.('isVideoPaused'))) return;
            this.scheduleAutoHide();
        });
    }
    
    /**
     * Setup click blocker - simplified
     * - When controls are hidden: tap shows controls
     * - When controls are visible: tap toggles play/pause
     */
    setupClickBlocker() {
        if (!this.clickBlocker || this.clickBlocker.hasAttribute('data-handler-attached')) return;
        
        this.clickBlocker.setAttribute('data-handler-attached', 'true');
        
        // Single handler for both touch and click
        const handleInteraction = (e) => {
            // Get coordinates from touch or mouse event
            const clientX = e.clientX ?? e.changedTouches?.[0]?.clientX ?? 0;
            const clientY = e.clientY ?? e.changedTouches?.[0]?.clientY ?? 0;
            
            // Prevent YouTube iframe from receiving the event
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            this.handleVideoTap(e, clientX, clientY);
        };
        
        // Touch handler
        this.clickBlocker.addEventListener('touchend', handleInteraction, { passive: false, capture: true });
        
        // Click handler
        this.clickBlocker.addEventListener('click', handleInteraction, { capture: true });
    }
    
    // Update controls state
    updateState(state) {
        // Ensure elements are set up
        if (!this.catGifContainer || !this.videoOverlay) {
            this.setupElements();
        }
        
        const playPauseBtn = document.getElementById('customPlayPause');
        if (playPauseBtn) {
            const playIcon = playPauseBtn.querySelector('.play-icon');
            const pauseIcon = playPauseBtn.querySelector('.pause-icon');
            
            if (state === YT.PlayerState.PLAYING) {
                // Show pause icon, hide play icon
                if (playIcon) playIcon.classList.add('d-none');
                if (pauseIcon) pauseIcon.classList.remove('d-none');
                this.hideCatGif();
                if (appState?.set) {
                    appState.set('isVideoPaused', false);
                }
                this.showControlBar();
                // Schedule auto-hide after a short delay to ensure control bar is visible
                // This ensures the fade-out happens smoothly
                setTimeout(() => {
                    this.scheduleAutoHide();
                }, 100);
                // Start progress update when video starts playing
                this.startProgressUpdate();
            } else if (state === YT.PlayerState.PAUSED || state === YT.PlayerState.CUED) {
                // Show play icon, hide pause icon
                if (playIcon) playIcon.classList.remove('d-none');
                if (pauseIcon) pauseIcon.classList.add('d-none');
                this.showCatGif();
                if (appState?.set) {
                    appState.set('isVideoPaused', true);
                }
                this.clearAutoHide();
                this.showControlBar();
                // Stop progress update when video is paused
                this.stopProgressUpdate();
            } else if (state === YT.PlayerState.ENDED) {
                // Show play icon, hide pause icon
                if (playIcon) playIcon.classList.remove('d-none');
                if (pauseIcon) pauseIcon.classList.add('d-none');
                this.hideCatGif();
                // Stop progress update when video ends
                this.stopProgressUpdate();
            } else {
                // Show play icon, hide pause icon
                if (playIcon) playIcon.classList.remove('d-none');
                if (pauseIcon) pauseIcon.classList.add('d-none');
                this.hideCatGif();
            }
        }
        
        if (eventEmitter?.emit) {
            eventEmitter.emit('controls:updated', { state: state });
        }
    }
    
    // Setup progress bar drag functionality
    setupProgressBarDrag(progressBar) {
        const startDrag = (e) => {
            this.isDragging = true;
            // Show control bar and prevent auto-hide during drag
            this.showControlBar();
            this.clearAutoHide();
            
            // Prevent click event from firing after drag
            progressBar.style.pointerEvents = 'none';
            
            this.handleProgressSeek(e, progressBar);
        };
        
        const drag = (e) => {
            if (!this.isDragging) return;
            // Keep control bar visible during drag
            this.showControlBar();
            this.clearAutoHide();
            
            // Use requestAnimationFrame for smoother updates
            requestAnimationFrame(() => {
                this.handleProgressSeek(e, progressBar);
            });
        };
        
        const endDrag = () => {
            this.isDragging = false;
            // Restore pointer events
            setTimeout(() => {
                progressBar.style.pointerEvents = '';
            }, 100);
            
            // Schedule auto-hide after drag ends (if video is playing)
            if (appState?.get && !appState.get('isVideoPaused')) {
                this.scheduleAutoHide();
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
            if (!this.isDragging) return;
            e.preventDefault();
            const touch = e.touches?.[0] || e.changedTouches?.[0];
            if (touch) {
                drag(touch);
            }
        }, { passive: false });
        
        document.addEventListener('touchend', endDrag);
        document.addEventListener('touchcancel', endDrag);
    }
    
    // Handle progress seek (used by both click and drag)
    handleProgressSeek(e, progressBar) {
        const videoPlayer = getVideoPlayer();
        const player = videoPlayer?.getPlayer?.();
        if (!player) return;
        
        try {
            const rect = progressBar.getBoundingClientRect();
            // Handle both mouse and touch events
            let clientX;
            if (e.clientX !== undefined) {
                clientX = e.clientX;
            } else if (e.touches?.[0]) {
                clientX = e.touches[0].clientX;
            } else if (e.changedTouches?.[0]) {
                clientX = e.changedTouches[0].clientX;
            } else {
                return;
            }
            
            const clickX = clientX - rect.left;
            const percent = Math.max(0, Math.min(1, clickX / rect.width)); // Clamp between 0 and 1
            
            const duration = player.getDuration();
            if (duration) {
                const newTime = duration * percent;
                
                // Update UI immediately BEFORE seeking (reduces lag)
                const progressFill = document.getElementById('customProgressFill');
                if (progressFill) {
                    progressFill.style.width = `${percent * 100}%`;
                }
                
                // Update time display immediately
                const currentTimeEl = document.getElementById('currentTime');
                if (currentTimeEl && formatTime) {
                    currentTimeEl.textContent = formatTime(newTime);
                }
                
                // Seek video (UI already updated, so this won't cause visible lag)
                if (videoPlayer?.seekTo) {
                    videoPlayer.seekTo(newTime);
                }
            }
        } catch (error) {
            // Silently handle seeking errors
        }
    }
    
    // Progress bar click handler (now just calls handleProgressSeek)
    handleProgressClick(e) {
        this.handleProgressSeek(e, e.currentTarget);
    }
    
    // Progress update
    startProgressUpdate() {
        if (this.progressUpdateInterval) {
            clearInterval(this.progressUpdateInterval);
        }
        
        this.progressUpdateInterval = setInterval(() => {
            const videoPlayer = getVideoPlayer();
            if (!videoPlayer?.isReady?.()) {
                this.stopProgressUpdate();
                return;
            }
            
            // Don't update progress if we're dragging (to prevent lag and conflicts)
            if (this.isDragging) return;
            
            try {
                const currentTime = videoPlayer.getCurrentTime?.();
                const duration = videoPlayer.getDuration?.();
                
                if (duration && !isNaN(currentTime)) {
                    const progressFill = document.getElementById('customProgressFill');
                    if (progressFill) {
                        const percent = (currentTime / duration) * 100;
                        progressFill.style.width = `${percent}%`;
                    }
                    
                    const currentTimeEl = document.getElementById('currentTime');
                    const totalTimeEl = document.getElementById('duration') || document.getElementById('totalTime');
                    if (currentTimeEl && formatTime) {
                        currentTimeEl.textContent = formatTime(currentTime);
                    }
                    if (totalTimeEl && formatTime) {
                        // Always update duration (it may change if video metadata loads)
                        totalTimeEl.textContent = formatTime(duration);
                    }
                    
                    if (eventEmitter?.emit) {
                        eventEmitter.emit('video:timeupdate', { currentTime: currentTime, duration: duration });
                    }
                }
            } catch (error) {
                // Silently handle progress update errors
            }
        }, 100);
    }
    
    stopProgressUpdate() {
        if (this.progressUpdateInterval) {
            clearInterval(this.progressUpdateInterval);
            this.progressUpdateInterval = null;
        }
    }
    
    // Cat GIF functions
    showCatGif() {
        if (!this.catGifContainer) {
            return;
        }
        
        // Apply overlay and blur immediately (don't wait for GIF to load)
        this.showPausedOverlay();
        
        // Get cat gifs from window (set by galleries/index.js or player/show.js)
        const gifs = (typeof window !== 'undefined' && window.availableCatGifs) || [];
        if (gifs.length === 0) {
            return;
        }
        
        // Try to load a GIF, with fallback if one fails
        this.tryLoadCatGif(gifs, 0);
    }
    
    /**
     * Show paused overlay (blur and dark overlay) immediately
     * This is called when video is paused, before GIF loads
     * Only blurs the video iframe, not navbar or control bar
     */
    showPausedOverlay() {
        const player = document.getElementById('player');
        if (player) {
            player.classList.add('paused');
        }
        
        if (this.videoOverlay) {
            this.videoOverlay.classList.add('active');
        }
    }
    
    /**
     * Hide paused overlay (blur and dark overlay)
     */
    hidePausedOverlay() {
        const player = document.getElementById('player');
        if (player) {
            player.classList.remove('paused');
        }
        
        // Find video overlay if not already set
        if (!this.videoOverlay) {
            this.videoOverlay = document.querySelector('.overlay-layer .video-overlay-effect');
        }
        if (!this.videoOverlay) {
            this.videoOverlay = document.querySelector('.video-overlay-effect');
        }
        
        if (this.videoOverlay) {
            this.videoOverlay.classList.remove('active');
        }
    }
    
    /**
     * Try to load a cat GIF with fallback mechanism
     * @param {string[]} gifs - Array of available GIF filenames
     * @param {number} attemptIndex - Current attempt index (to avoid infinite loops)
     */
    tryLoadCatGif(gifs, attemptIndex) {
        if (attemptIndex >= gifs.length || attemptIndex >= 10) {
            // Prevent infinite loops - max 10 attempts
            return;
        }
        
        // Select a random GIF, but avoid the one we just tried if this is a retry
        let selectedGif;
        if (attemptIndex === 0) {
            selectedGif = gifs[Math.floor(Math.random() * gifs.length)];
        } else {
            // On retry, pick a different GIF
            const availableGifs = gifs.filter(gif => gif !== this.currentCatGif);
            if (availableGifs.length === 0) {
                // If all GIFs were tried, reset and try again
                this.currentCatGif = null;
                selectedGif = gifs[Math.floor(Math.random() * gifs.length)];
            } else {
                selectedGif = availableGifs[Math.floor(Math.random() * availableGifs.length)];
            }
        }
        
        this.currentCatGif = selectedGif;
        
        // Properly encode the filename for URL (handles spaces and special characters)
        const encodedGif = encodeURIComponent(selectedGif);
        const gifUrl = `/assets/cats/${encodedGif}`;
        
        // Ensure image element exists
        if (!this.catGifImage) {
            this.catGifImage = this.catGifContainer.querySelector('img');
            if (!this.catGifImage) {
                return;
            }
        }
        
        // Remove old event listeners if they exist
        this.catGifImage.onload = null;
        this.catGifImage.onerror = null;
        
        // Prepare container for GIF display (overlay already shown by showCatGif)
        // Remove d-none class first, then set display to flex (CSS will handle positioning)
        this.catGifContainer.classList.remove('hide', 'd-none');
        // Set display to flex to override d-none CSS rule
        this.catGifContainer.style.display = 'flex';
        this.catGifContainer.style.backgroundColor = '';
        
        // Handle successful load
        this.catGifImage.onload = () => {
            // Ensure overlay is still visible (in case it was hidden)
            this.showPausedOverlay();
            
            // Force reflow and ensure display is set
            this.catGifContainer.style.display = 'flex';
            void this.catGifContainer.offsetHeight;
            
            // Use requestAnimationFrame for better compatibility
            const showGif = () => {
                this.catGifContainer.classList.add('show');
                // Explicitly set opacity as fallback
                this.catGifContainer.style.opacity = '1';
            };
            
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(() => {
                    setTimeout(showGif, 50);
                });
            } else {
                setTimeout(showGif, 50);
            }
        };
        
        // Handle load error - try another GIF
        this.catGifImage.onerror = () => {
            // Try next GIF
            this.tryLoadCatGif(gifs, attemptIndex + 1);
        };
        
        // Set source to trigger load (or use cached version)
        this.catGifImage.src = gifUrl;
        
        // If image is already loaded (cached), trigger onload manually
        if (this.catGifImage.complete && this.catGifImage.naturalHeight !== 0) {
            // Image is already loaded, trigger onload manually
            if (this.catGifImage.onload) {
                this.catGifImage.onload();
            }
        }
    }
    
    hideCatGif() {
        if (!this.catGifContainer) return;
        
        this.catGifContainer.classList.remove('show');
        this.catGifContainer.classList.add('hide');
        
        // Hide overlay and blur
        this.hidePausedOverlay();
        
        setTimeout(() => {
            this.catGifContainer.classList.add('d-none');
            this.catGifContainer.classList.remove('hide');
            this.currentCatGif = null;
        }, 550);
    }
    
}

// Create instance and export
export const controls = new Controls();

// Also attach to global namespace for backward compatibility during transition
if (typeof window !== 'undefined') {
    if (!window.Traktor) {
        window.Traktor = {};
    }
    if (!window.Traktor.Modules) {
        window.Traktor.Modules = {};
    }
    window.Traktor.Modules.Controls = Controls;
    window.Traktor.Modules.controls = controls;
}
