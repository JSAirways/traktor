/**
 * Fullscreen Module
 * Handles fullscreen functionality for video player
 */

import { appState } from '../core/state.js';
import { eventEmitter } from '../core/events.js';
import { isFullscreenSupported } from '../core/utils.js';

// Import navbar dynamically to avoid circular dependency
let navbar = null;
function getNavbar() {
    if (!navbar && typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        navbar = window.Traktor.Modules.navbar;
    }
    return navbar;
}

export class Fullscreen {
    constructor() {
        this.playerView = document.querySelector('.player-view');
        this._isSupported = null; // Lazy evaluation
        this.init();
    }
    
    // Lazy getter for isSupported
    getIsSupported() {
        if (this._isSupported === null) {
            this._isSupported = isFullscreenSupported ? isFullscreenSupported() : false;
        }
        return this._isSupported;
    }
    
    // Getter for isSupported property (for backward compatibility)
    get isSupported() {
        return this.getIsSupported();
    }
    
    init() {
        // Hide fullscreen button if not supported
        this.hideButtonIfNotSupported();
        
        // Setup fullscreen change listeners
        this.setupListeners();
        
        // Setup controls (button and double-click/tap) when ready
        this.setupControls();
    }
    
    // Hide fullscreen button if fullscreen is not supported
    hideButtonIfNotSupported() {
        if (!this.getIsSupported()) {
            const fullscreenBtn = document.getElementById('customFullscreen');
            if (fullscreenBtn) {
                fullscreenBtn.style.display = 'none';
            }
        }
    }
    
    // Setup all control-related functionality
    setupControls() {
        // Listen for when controls are ready to check/hide button and attach handler
        if (eventEmitter && eventEmitter.on) {
            eventEmitter.on('controls:ready', () => {
                // Check and hide button if not supported
                this.hideButtonIfNotSupported();
                // Attach button handler if supported
                this.attachButtonHandler();
            });
        }
        // Also check immediately in case button already exists
        setTimeout(() => {
            this.hideButtonIfNotSupported();
            this.attachButtonHandler();
        }, 100);
        
        // Try again after a longer delay to ensure button is in DOM
        setTimeout(() => {
            this.attachButtonHandler();
        }, 500);
    }
    
    // Attach click handler to fullscreen button
    attachButtonHandler() {
        const fullscreenBtn = document.getElementById('customFullscreen');
        if (!fullscreenBtn) return;
        
        // Hide button if not supported
        if (!this.getIsSupported()) {
            fullscreenBtn.style.display = 'none';
            return;
        }
        
        // Check if handler is already attached
        if (fullscreenBtn.hasAttribute('data-fullscreen-handler-attached')) {
            return;
        }
        
        fullscreenBtn.setAttribute('data-fullscreen-handler-attached', 'true');
        
        // Prevent touchstart from propagating
        fullscreenBtn.addEventListener('touchstart', (e) => {
            e.stopPropagation();
        }, { passive: true });
        
        // Click handler
        fullscreenBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (this.toggle) {
                this.toggle();
            }
            // Show control bar and schedule auto-hide after fullscreen toggle
            const controls = window.Traktor?.Modules?.controls;
            if (controls?.showControlBar) {
                controls.showControlBar();
                if (controls?.scheduleAutoHideInternal) {
                    controls.scheduleAutoHideInternal();
                }
            }
        });
        
        // Touch handler for compatibility
        fullscreenBtn.addEventListener('touchend', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (this.toggle) {
                this.toggle();
            }
            // Show control bar and schedule auto-hide after fullscreen toggle
            const controls = window.Traktor?.Modules?.controls;
            if (controls?.showControlBar) {
                controls.showControlBar();
                if (controls?.scheduleAutoHideInternal) {
                    controls.scheduleAutoHideInternal();
                }
            }
        }, { passive: false });
    }
    
    // Check if currently in fullscreen
    isFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement || 
                  document.mozFullScreenElement || document.msFullscreenElement);
    }
    
    // Toggle fullscreen
    toggle() {
        if (!this.getIsSupported() || !this.playerView) return;
        
        try {
            if (this.isFullscreen()) {
                this.exit();
            } else {
                this.enter();
            }
        } catch (error) {
            // Silently handle fullscreen toggle errors
        }
    }
    
    // Enter fullscreen
    enter() {
        if (!this.getIsSupported() || !this.playerView) return;
        
        // Move navbar into player-view for fullscreen
        const navbarInstance = getNavbar();
        if (navbarInstance && navbarInstance.moveToFullscreen) {
            navbarInstance.moveToFullscreen(this.playerView);
        }
        
        // Standard fullscreen API
        if (this.playerView.requestFullscreen) {
            this.playerView.requestFullscreen();
        } else if (this.playerView.webkitRequestFullscreen) {
            this.playerView.webkitRequestFullscreen();
        } else if (this.playerView.mozRequestFullScreen) {
            this.playerView.mozRequestFullScreen();
        } else if (this.playerView.msRequestFullscreen) {
            this.playerView.msRequestFullscreen();
        }
    }
    
    // Exit fullscreen
    exit() {
        // Standard fullscreen API
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
    
    // Update fullscreen button icon
    updateButtonIcon() {
        if (!this.getIsSupported()) return;
        
        const fullscreenBtn = document.getElementById('customFullscreen');
        if (!fullscreenBtn) return;
        
        if (this.isFullscreen()) {
            fullscreenBtn.innerHTML = '<i class="bi bi-fullscreen-exit fs-4"></i>';
        } else {
            fullscreenBtn.innerHTML = '<i class="bi bi-arrows-fullscreen fs-4"></i>';
        }
    }
    
    // Setup fullscreen change listeners
    setupListeners() {
        const events = ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'];
        
        for (const eventType of events) {
            document.addEventListener(eventType, () => {
                const isFullscreen = this.isFullscreen();
                if (appState && appState.set) {
                    appState.set('isFullscreen', isFullscreen);
                }
                this.updateButtonIcon();
                
                if (!isFullscreen) {
                    // Restore navbar from fullscreen
                    const navbarInstance = getNavbar();
                    if (navbarInstance && navbarInstance.restoreFromFullscreen) {
                        navbarInstance.restoreFromFullscreen();
                    }
                }
                
                if (eventEmitter && eventEmitter.emit) {
                    eventEmitter.emit('fullscreen:change', { isFullscreen: isFullscreen });
                }
            });
        }
    }
    
    // Setup double-click/tap for fullscreen toggle
    setupDoubleClick() {
        const playerElement = document.getElementById('videoContainer');
        const controlsElement = document.querySelector('.custom-controls');
        if (!playerElement || !controlsElement) return;
        
        const clickBlocker = controlsElement.querySelector('.custom-click-blocker');
        if (!clickBlocker) return;
        
        // Double-click (mouse) - handled by player page
        // Double-tap (touch) - handled by player page
        // This method is kept for backward compatibility but does nothing
    }
    
    // Exit fullscreen before switching to gallery
    async exitBeforeGallerySwitch() {
        if (this.isFullscreen()) {
            this.exit();
            // Wait a bit for fullscreen to exit before switching views
            return new Promise((resolve) => {
                const checkExit = () => {
                    if (!this.isFullscreen()) {
                        resolve();
                    } else {
                        setTimeout(checkExit, 50);
                    }
                };
                checkExit();
            });
        }
        return Promise.resolve();
    }
}

// Create instance and export
export const fullscreen = new Fullscreen();

// Also attach to global namespace for backward compatibility during transition
if (typeof window !== 'undefined') {
    if (!window.Traktor) {
        window.Traktor = {};
    }
    if (!window.Traktor.Modules) {
        window.Traktor.Modules = {};
    }
    window.Traktor.Modules.Fullscreen = Fullscreen;
    window.Traktor.Modules.fullscreen = fullscreen;
}
