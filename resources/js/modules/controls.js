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

function getFullscreen() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.fullscreen;
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
        this.lastSideTap = { time: 0, side: null };
        this.pendingTapTimeout = null;
        this.lastTouchEndTime = 0;
        this.pendingSeekTime = null;
        this.lastSeekAt = 0;
        this.seekVideoId = null;
        this._hoverHandlersAttached = false;
        this._autoHideAttached = false;
        this._keyboardAttached = false;
        this.init();
    }
    
    init() {
        // All elements are in Blade template - just find and set up handlers
        this.setupElements();
        this.setupKeyboardShortcuts();
        
        // Initialize when player is ready
        if (eventEmitter?.on) {
            eventEmitter.on('player:ready', () => {
                this.setupElements();
                this.syncPlaybackSpeedLabel();
            });
            
            eventEmitter.on('view:player-shown', () => {
                setTimeout(() => {
                    this.setupElements();
                    this.syncPlaybackSpeedLabel();
                }, 200);
            });
            
            // If player is already ready, setup immediately
            if (appState?.get?.('playerReady')) {
                setTimeout(() => {
                    this.setupElements();
                    this.syncPlaybackSpeedLabel();
                }, 100);
            }
            
            // Listen for video state changes
            eventEmitter.on('video:statechange', (data) => {
                this.updateState(data.state);
            });

            eventEmitter.on('video:play', () => {
                this.syncPlaybackSpeedLabel();
            });

            eventEmitter.on('fullscreen:change', () => {
                this.reveal();
                this.bumpAutoHide();
            });
        }
    }

    /**
     * Drop optimistic seek state (e.g. when the active video changes)
     */
    clearPendingSeek() {
        this.pendingSeekTime = null;
        this.lastSeekAt = 0;
    }

    /**
     * Invalidate pending seek when the loaded video id changes
     */
    clearPendingSeekIfVideoChanged() {
        const videoId = appState?.get?.('currentVideoId') ?? null;
        if (videoId !== this.seekVideoId) {
            this.seekVideoId = videoId;
            this.clearPendingSeek();
        }
    }

    /**
     * Stamp last touch action time (shared ghost-click filter)
     */
    markTouchAction() {
        this.lastTouchEndTime = Date.now();
    }

    /**
     * Whether a click is a synthetic ghost click after touch
     * @returns {boolean}
     */
    isGhostClick() {
        return Date.now() - this.lastTouchEndTime < 500;
    }
    
    // Setup elements from Blade template
    setupElements() {
        // Find elements (all should exist in Blade template)
        this.playerElement = document.getElementById('videoContainer');
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
            this.markTouchAction();
            this.showControlBar();
            if (appState?.get && !appState.get('isVideoPaused')) {
                this.scheduleAutoHide();
            }
        }, { passive: false });
        controlBar.addEventListener('click', (e) => e.stopPropagation());
        controlBar.addEventListener('mouseenter', () => {
            this.showControlBar();
            this.clearAutoHide();
        });
        controlBar.addEventListener('mouseleave', () => {
            if (this.isDragging || (appState?.get?.('isVideoPaused'))) return;
            this.scheduleAutoHide();
        });
        
        // Setup play/pause button
        const playPauseBtn = document.getElementById('customPlayPause');
        const videoPlayer = getVideoPlayer();
        if (playPauseBtn && !playPauseBtn.hasAttribute('data-handler-attached')) {
            playPauseBtn.setAttribute('data-handler-attached', 'true');
            
            playPauseBtn.addEventListener('touchstart', (e) => e.stopPropagation(), { passive: true });
            
            const handlePlayPause = (e) => {
                e.stopPropagation();
                e.preventDefault();
                if (e.type === 'click' && this.isGhostClick()) return;
                if (e.type === 'touchend') this.markTouchAction();

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
                if (this.isGhostClick()) return;
                this.handleProgressClick(e);
            });
            this.setupProgressBarDrag(progressBar);
        }

        this.setupPlaybackSpeedButton();
        
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

    /**
     * Cycle playback speed: 1x → 1.5x → 1.75x → 2x → 1x
     */
    setupPlaybackSpeedButton() {
        const speedBtn = document.getElementById('customPlaybackSpeed');
        if (!speedBtn || speedBtn.hasAttribute('data-handler-attached')) {
            this.syncPlaybackSpeedLabel();
            return;
        }

        speedBtn.setAttribute('data-handler-attached', 'true');
        speedBtn.addEventListener('touchstart', (e) => e.stopPropagation(), { passive: true });

        const handleSpeedToggle = (e) => {
            e.stopPropagation();
            e.preventDefault();
            if (e.type === 'click' && this.isGhostClick()) return;
            if (e.type === 'touchend') this.markTouchAction();

            const rates = [1, 1.5, 1.75, 2];
            const current = Number(appState?.get?.('playbackRate') || speedBtn.dataset.speed || 1);
            const currentIndex = rates.findIndex((rate) => Math.abs(rate - current) < 0.001);
            const nextRate = rates[(currentIndex + 1) % rates.length];

            const videoPlayer = getVideoPlayer();
            if (videoPlayer?.setPlaybackRate) {
                videoPlayer.setPlaybackRate(nextRate);
            } else if (appState?.set) {
                appState.set('playbackRate', nextRate);
            }

            this.syncPlaybackSpeedLabel(nextRate);
            this.showControlBar();
            if (appState?.get && !appState.get('isVideoPaused')) {
                this.scheduleAutoHide();
            }
        };

        speedBtn.addEventListener('click', handleSpeedToggle);
        speedBtn.addEventListener('touchend', handleSpeedToggle, { passive: false });
        this.syncPlaybackSpeedLabel();
    }

    /**
     * Update the speed button label/aria from the current rate
     * @param {number|null} rate
     */
    syncPlaybackSpeedLabel(rate = null) {
        const speedBtn = document.getElementById('customPlaybackSpeed');
        const label = document.getElementById('customPlaybackSpeedLabel');
        if (!speedBtn || !label) return;

        const value = rate ?? Number(appState?.get?.('playbackRate') || speedBtn.dataset.speed || 1);
        const display = Number.isInteger(value) ? `${value}x` : `${value}x`;
        label.textContent = display;
        speedBtn.dataset.speed = String(value);
        speedBtn.setAttribute('aria-label', `${getTranslation?.('gallery.playback_speed', 'Playback speed') || 'Playback speed'}: ${display}`);
        speedBtn.setAttribute('title', display);
    }
    /**
     * Public alias — show control bar + navbar
     */
    reveal() {
        this.showControlBar();
    }

    /**
     * Public alias — restart auto-hide timer
     */
    bumpAutoHide() {
        this.scheduleAutoHide();
    }
    
    // Show control bar and navbar
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
    
    // Hide control bar and navbar
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
        
        const autoHideDelay = TimingConstants?.AUTO_HIDE_DELAY || 3000;
        
        this.controlBarAutoHideTimeout = setTimeout(() => {
            if (!this.isDragging && appState?.get && !appState.get('isVideoPaused')) {
                this.hideControlBar();
            }
        }, autoHideDelay);
    }
    
    setupAutoHide() {
        if (this._autoHideAttached) return;

        this.playerElement = document.getElementById('videoContainer') || this.playerElement;
        if (!this.playerElement) return;

        this._autoHideAttached = true;
        
        // Throttled mousemove on video container (hover path is primary; this is a backup)
        let lastMousemoveTime = 0;
        this.playerElement.addEventListener('mousemove', () => {
            if (this.isDragging) return;
            const now = Date.now();
            if (now - lastMousemoveTime < 200) return;
            lastMousemoveTime = now;
            this.showControlBar();
            this.scheduleAutoHide();
        });
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
        const controlElementIds = ['customPlayPause', 'customFullscreen', 'customPlaybackSpeed', 'customProgressBar', 'customProgressFill', 'customTimeDisplay'];
        const controlElementClasses = ['custom-control-btn', 'custom-progress-container', 'custom-progress-bar', 'custom-progress-fill', 'custom-time-display', 'custom-playback-speed'];
        
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
     * Handle video area tap/click
     * - Mouse: always toggle play/pause (hover already shows controls)
     * - Touch with controls hidden: show controls only
     * - Touch with controls visible: toggle play/pause
     * @param {Event} e
     * @param {number} clientX
     * @param {number} clientY
     * @param {{ isTouch?: boolean }} options
     */
    handleVideoTap(e, clientX, clientY, options = {}) {
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
        
        const isTouch = options.isTouch === true;
        const controlBar = document.querySelector('.custom-control-bar') || document.getElementById('customControlBar');
        const isHidden = controlBar?.classList.contains('hidden');

        // Touch + hidden controls: reveal only (no pause) — mobile expected UX
        if (isTouch && isHidden) {
            const videoPlayer = getVideoPlayer();
            if (videoPlayer?.isReady?.()) {
                const playerState = videoPlayer.getPlayerState?.();
                // If video was paused by YouTube's click handler - resume it
                if (appState?.get && !appState.get('isVideoPaused') && playerState === YT.PlayerState.PAUSED) {
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
            if (appState?.get && !appState.get('isVideoPaused')) {
                this.scheduleAutoHide();
            }
            return;
        }

        // Mouse click, or touch while controls are visible: toggle play/pause
        const videoPlayer = getVideoPlayer();
        if (!videoPlayer?.isReady?.()) {
            return;
        }

        this.showControlBar();
        videoPlayer.togglePlayPause?.();

        const currentState = videoPlayer.getPlayerState?.();
        if (currentState !== YT.PlayerState.PLAYING) {
            setTimeout(() => {
                if (videoPlayer.getPlayerState?.() === YT.PlayerState.PLAYING) {
                    this.scheduleAutoHide();
                }
            }, 100);
        }
    }

    /**
     * Whether an interaction came from a touch surface
     * @param {Event} e
     * @returns {boolean}
     */
    isTouchEvent(e) {
        if (!e) return false;
        if (e.type && e.type.startsWith('touch')) return true;
        if (e.pointerType === 'touch') return true;
        if (e.sourceCapabilities && e.sourceCapabilities.firesTouchEvents) return true;
        return false;
    }

    /**
     * Map X position to left / center / right scrub zone
     * @param {number} clientX
     * @returns {'left'|'center'|'right'}
     */
    getTapSide(clientX) {
        const rect = this.clickBlocker?.getBoundingClientRect?.()
            || this.playerElement?.getBoundingClientRect?.()
            || { left: 0, width: window.innerWidth || 0 };
        const width = rect.width || window.innerWidth || 1;
        const x = clientX - (rect.left || 0);
        const zone = TimingConstants?.SEEK_SIDE_ZONE ?? 0.33;
        const ratio = x / width;
        if (ratio <= zone) return 'left';
        if (ratio >= 1 - zone) return 'right';
        return 'center';
    }

    /**
     * Playback time for UI / relative seek while a seek is still settling.
     * Prevents the progress bar from snapping back to stale YouTube times.
     * @param {number} playerTime
     * @returns {number}
     */
    getEffectivePlaybackTime(playerTime) {
        this.clearPendingSeekIfVideoChanged();

        if (this.pendingSeekTime == null) {
            return playerTime;
        }

        const now = Date.now();
        const seekSettleMs = TimingConstants?.SEEK_SETTLE_MS ?? 750;
        const elapsed = now - this.lastSeekAt;

        // Player caught up to the optimistic target
        if (Math.abs(playerTime - this.pendingSeekTime) < 0.85) {
            this.pendingSeekTime = null;
            return playerTime;
        }

        // Still within settle window — keep showing optimistic time
        if (elapsed < seekSettleMs) {
            return this.pendingSeekTime;
        }

        // Settle window expired; trust the player again
        this.pendingSeekTime = null;
        return playerTime;
    }

    /**
     * Seek relative to current playback position.
     * Uses an optimistic pending time so rapid arrow keys accumulate instead of
     * re-reading a stale YouTube getCurrentTime() mid-seek.
     * @param {number} seconds
     */
    seekRelative(seconds) {
        const videoPlayer = getVideoPlayer();
        if (!videoPlayer?.isReady?.() || !videoPlayer.seekTo) return;

        try {
            this.clearPendingSeekIfVideoChanged();

            const playerTime = videoPlayer.getCurrentTime?.() || 0;
            const duration = videoPlayer.getDuration?.() || 0;
            if (!duration || isNaN(duration)) return;

            const base = this.getEffectivePlaybackTime(playerTime);
            const newTime = Math.max(0, Math.min(duration, base + seconds));

            this.pendingSeekTime = newTime;
            this.lastSeekAt = Date.now();
            videoPlayer.seekTo(newTime);

            const currentTimeEl = document.getElementById('currentTime');
            if (currentTimeEl && formatTime) {
                currentTimeEl.textContent = formatTime(newTime);
            }
            if (duration > 0) {
                const fill = document.getElementById('customProgressFill');
                if (fill) {
                    fill.style.width = `${(newTime / duration) * 100}%`;
                }
            }

            this.showControlBar();
            if (appState?.get && !appState.get('isVideoPaused')) {
                this.scheduleAutoHide();
            }
        } catch (error) {
            // Silently handle seek errors
        }
    }
    
    /**
     * Setup hover handlers for showing controls on mouse move/hover
     */
    setupHoverHandlers() {
        if (this._hoverHandlersAttached) return;

        const hoverTarget = document.querySelector('.player-view') || this.clickBlocker;
        if (!hoverTarget) return;

        this._hoverHandlersAttached = true;

        const showOnMouseMove = (e) => {
            if (this.isDragging) return;
            if (this.isTouchEvent(e)) return;
            this.showControlBar();
            this.clearAutoHide();
            if (appState?.get && !appState.get('isVideoPaused')) {
                this.scheduleAutoHide();
            }
        };

        const hideOnMouseLeave = () => {
            if (this.isDragging || (appState?.get?.('isVideoPaused'))) return;
            this.scheduleAutoHide();
        };

        hoverTarget.addEventListener('mousemove', showOnMouseMove);
        hoverTarget.addEventListener('mouseenter', showOnMouseMove);
        hoverTarget.addEventListener('mouseleave', hideOnMouseLeave);

        if (this.clickBlocker && this.clickBlocker !== hoverTarget) {
            this.clickBlocker.addEventListener('mousemove', showOnMouseMove);
            this.clickBlocker.addEventListener('mouseenter', showOnMouseMove);
        }
    }
    
    /**
     * Setup click blocker interactions
     * - Mouse click: toggle play/pause (controls shown via hover)
     * - Touch: first tap shows controls; tap again toggles play/pause
     * - Double-tap left/right: seek ± SEEK_SECONDS
     * - Double-tap center / mouse dblclick: toggle fullscreen
     */
    setupClickBlocker() {
        if (!this.clickBlocker || this.clickBlocker.hasAttribute('data-handler-attached')) return;
        
        this.clickBlocker.setAttribute('data-handler-attached', 'true');

        const seekSeconds = TimingConstants?.SEEK_SECONDS ?? 10;
        const doubleDelay = TimingConstants?.DOUBLE_CLICK_DELAY ?? 300;

        const handleTouchEnd = (e) => {
            const touch = e.changedTouches?.[0];
            const clientX = touch?.clientX ?? 0;
            const clientY = touch?.clientY ?? 0;

            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            this.markTouchAction();

            const side = this.getTapSide(clientX);
            const now = Date.now();
            const isDoubleTap = this.lastSideTap.side === side
                && (now - this.lastSideTap.time) < doubleDelay;

            if (isDoubleTap) {
                if (this.pendingTapTimeout) {
                    clearTimeout(this.pendingTapTimeout);
                    this.pendingTapTimeout = null;
                }
                this.lastSideTap = { time: 0, side: null };

                if (side === 'left') {
                    this.seekRelative(-seekSeconds);
                } else if (side === 'right') {
                    this.seekRelative(seekSeconds);
                } else {
                    const fullscreen = getFullscreen();
                    if (fullscreen?.toggle && fullscreen.getIsSupported?.()) {
                        fullscreen.toggle();
                        this.reveal();
                        this.bumpAutoHide();
                    }
                }
                return;
            }

            this.lastSideTap = { time: now, side };

            if (this.pendingTapTimeout) {
                clearTimeout(this.pendingTapTimeout);
            }
            this.pendingTapTimeout = setTimeout(() => {
                this.pendingTapTimeout = null;
                this.handleVideoTap(e, clientX, clientY, { isTouch: true });
            }, doubleDelay);
        };

        const handleMouseClick = (e) => {
            if (this.isGhostClick()) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }

            const clientX = e.clientX ?? 0;
            const clientY = e.clientY ?? 0;

            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            this.handleVideoTap(e, clientX, clientY, { isTouch: false });
        };

        const handleDblClick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            const fullscreen = getFullscreen();
            if (fullscreen?.toggle && fullscreen.getIsSupported?.()) {
                fullscreen.toggle();
                this.reveal();
                this.bumpAutoHide();
            }
        };
        
        this.clickBlocker.addEventListener('touchend', handleTouchEnd, { passive: false, capture: true });
        this.clickBlocker.addEventListener('click', handleMouseClick, { capture: true });
        this.clickBlocker.addEventListener('dblclick', handleDblClick);
    }

    /**
     * Keyboard shortcuts — Space, F, arrows
     */
    setupKeyboardShortcuts() {
        if (this._keyboardAttached) return;
        this._keyboardAttached = true;

        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }

            const videoPlayer = getVideoPlayer();
            const fullscreen = getFullscreen();
            const seekSeconds = TimingConstants?.SEEK_SECONDS ?? 10;

            if (e.code === 'Space') {
                e.preventDefault();
                if (videoPlayer?.togglePlayPause) {
                    videoPlayer.togglePlayPause();
                }
                this.reveal();
                this.bumpAutoHide();
                return;
            }

            if (e.code === 'KeyF') {
                e.preventDefault();
                if (fullscreen?.toggle && fullscreen.getIsSupported?.()) {
                    fullscreen.toggle();
                }
                this.reveal();
                this.bumpAutoHide();
                return;
            }

            if (e.code === 'ArrowLeft') {
                e.preventDefault();
                this.seekRelative(-seekSeconds);
                return;
            }

            if (e.code === 'ArrowRight') {
                e.preventDefault();
                this.seekRelative(seekSeconds);
            }
        });
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
                this.clearPendingSeek();
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
                    this.pendingSeekTime = newTime;
                    this.lastSeekAt = Date.now();
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
                const playerTime = videoPlayer.getCurrentTime?.();
                const duration = videoPlayer.getDuration?.();
                
                if (duration && !isNaN(playerTime)) {
                    const currentTime = this.getEffectivePlaybackTime(playerTime);
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
        }, TimingConstants?.PROGRESS_UPDATE_INTERVAL || 100);
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
