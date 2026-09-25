/**
 * Fullscreen Module
 * Handles fullscreen functionality for video player
 */

import { appState } from '../core/state.js';
import { eventEmitter } from '../core/events.js';
import { isFullscreenSupported } from '../core/utils.js';

let navbar = null;
function getNavbar() {
    if (!navbar && typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        navbar = window.Traktor.Modules.navbar;
    }
    return navbar;
}

function getControls() {
    return window.Traktor?.Modules?.controls ?? null;
}

export class Fullscreen {
    constructor() {
        this.playerView = document.querySelector('.player-view');
        this._isSupported = null;
        this._lastTouchEndTime = 0;
        this.init();
    }
    
    getIsSupported() {
        if (this._isSupported === null) {
            this._isSupported = isFullscreenSupported ? isFullscreenSupported() : false;
        }
        return this._isSupported;
    }
    
    get isSupported() {
        return this.getIsSupported();
    }
    
    init() {
        this.hideButtonIfNotSupported();
        this.setupListeners();
        this.setupControls();
    }
    
    hideButtonIfNotSupported() {
        if (!this.getIsSupported()) {
            const fullscreenBtn = document.getElementById('customFullscreen');
            if (fullscreenBtn) {
                fullscreenBtn.style.display = 'none';
            }
        }
    }
    
    setupControls() {
        if (eventEmitter && eventEmitter.on) {
            eventEmitter.on('controls:ready', () => {
                this.hideButtonIfNotSupported();
                this.attachButtonHandler();
            });
        }
        setTimeout(() => {
            this.hideButtonIfNotSupported();
            this.attachButtonHandler();
        }, 100);
        
        setTimeout(() => {
            this.attachButtonHandler();
        }, 500);
    }
    
    attachButtonHandler() {
        const fullscreenBtn = document.getElementById('customFullscreen');
        if (!fullscreenBtn) return;
        
        if (!this.getIsSupported()) {
            fullscreenBtn.style.display = 'none';
            return;
        }
        
        if (fullscreenBtn.hasAttribute('data-fullscreen-handler-attached')) {
            return;
        }
        
        fullscreenBtn.setAttribute('data-fullscreen-handler-attached', 'true');
        
        fullscreenBtn.addEventListener('touchstart', (e) => {
            e.stopPropagation();
        }, { passive: true });

        const handleToggle = (e) => {
            e.preventDefault();
            e.stopPropagation();

            if (e.type === 'click' && Date.now() - this._lastTouchEndTime < 500) {
                return;
            }
            if (e.type === 'touchend') {
                this._lastTouchEndTime = Date.now();
                const controls = getControls();
                controls?.markTouchAction?.();
            }

            this.toggle();

            const controls = getControls();
            controls?.reveal?.();
            controls?.bumpAutoHide?.();
        };
        
        fullscreenBtn.addEventListener('click', handleToggle);
        fullscreenBtn.addEventListener('touchend', handleToggle, { passive: false });
    }
    
    isFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement || 
                  document.mozFullScreenElement || document.msFullscreenElement);
    }
    
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
    
    enter() {
        if (!this.getIsSupported() || !this.playerView) return;
        
        const navbarInstance = getNavbar();
        if (navbarInstance && navbarInstance.moveToFullscreen) {
            navbarInstance.moveToFullscreen(this.playerView);
        }
        
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
    
    exit() {
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
    
    async exitBeforeGallerySwitch() {
        if (this.isFullscreen()) {
            this.exit();
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

export const fullscreen = new Fullscreen();

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
