/**
 * PWA Installer Module
 * Handles service worker registration and PWA installation prompts
 */

import { eventEmitter } from './events.js';
import { getTranslation, showToast } from './utils.js';

let deferredPrompt = null;
let clickHandlerBound = false;

/**
 * Registers the service worker for offline functionality
 * @returns {Promise<ServiceWorkerRegistration|null>}
 */
export function registerServiceWorker() {
    // Check for support and fail gracefully
    if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) {
        return Promise.resolve(null);
    }
    
    try {
        return navigator.serviceWorker.register('/sw.js', {
            scope: '/'
        }).then(registration => {
            // Handle service worker updates
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
        
                if (newWorker) {
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // New service worker available - emit event for UI to handle
                            if (eventEmitter && eventEmitter.emit) {
                                eventEmitter.emit('pwa-update-available', true);
                            }
                        }
                    });
                }
            });

            return registration;
        }).catch(error => {
            // Silently handle service worker registration errors
            return null;
        });
    } catch (error) {
        // Silently handle service worker registration errors
        return Promise.resolve(null);
    }
}

/**
 * Shows the install button if it exists in the DOM
 */
function showInstallButton() {
    const installBtns = document.querySelectorAll('#pwaInstallBtn');
    installBtns.forEach(btn => {
        btn.classList.remove('d-none');
    });
}

/**
 * Hides the install button if it exists in the DOM
 */
function hideInstallButton() {
    const installBtns = document.querySelectorAll('#pwaInstallBtn');
    installBtns.forEach(btn => {
        btn.classList.add('d-none');
    });
}

/**
 * Whether the app is already running as an installed PWA
 */
function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

/**
 * Detect iOS / iPadOS Safari (no beforeinstallprompt support)
 */
function isIosSafari() {
    const ua = window.navigator.userAgent || '';
    const isIos = /iPad|iPhone|iPod/.test(ua)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isWebkit = /WebKit/.test(ua) && !/CriOS|FxiOS|OPiOS|EdgiOS/.test(ua);
    return isIos && isWebkit;
}

/**
 * User-facing message when the browser cannot show the native install prompt
 */
function getInstallUnavailableMessage() {
    if (isIosSafari()) {
        return getTranslation(
            'common.install_ios_instructions',
            'To install: tap Share, then “Add to Home Screen”.'
        );
    }

    return getTranslation(
        'common.install_unavailable',
        'Use your browser menu (Install app / Apps) to install Traktor, or open the site in Chrome or Edge.'
    );
}

/**
 * Handles install prompt events and manages install button visibility
 * Uses eventEmitter for cross-module communication
 */
export function handleInstallPrompt() {
    // Check if app is already installed
    if (isStandalone()) {
        hideInstallButton();
        return;
    }

    // Listen for beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent the mini-infobar from appearing
        e.preventDefault();
        // Stash the event so it can be triggered later
        deferredPrompt = e;
        
        // Emit event for UI to show install button
        if (eventEmitter && eventEmitter.emit) {
            eventEmitter.emit('pwa-installable', true);
        }
        
        // Show install button only when the browser can actually install
        showInstallButton();
    });

    // Handle app installed event
    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        if (eventEmitter && eventEmitter.emit) {
            eventEmitter.emit('pwa-installed', true);
        }
        
        // Hide install button
        hideInstallButton();
    });
}

/**
 * Prompts the user to install the PWA
 * @returns {Promise<boolean>} True if user accepted, false if dismissed / unavailable
 */
export async function promptInstall() {
    if (!deferredPrompt) {
        // Browser cannot show native install UI — explain how to install manually
        if (showToast) {
            showToast(getInstallUnavailableMessage(), 'info', 8000);
        }
        return false;
    }

    try {
        deferredPrompt.prompt();
        const choice = await deferredPrompt.userChoice;
        const outcome = choice.outcome;
    
        if (outcome === 'accepted') {
            if (eventEmitter && eventEmitter.emit) {
                eventEmitter.emit('pwa-install-accepted', true);
            }
            return true;
        } else {
            if (eventEmitter && eventEmitter.emit) {
                eventEmitter.emit('pwa-install-dismissed', true);
            }
            return false;
        }
    } catch (error) {
        if (showToast) {
            showToast(getInstallUnavailableMessage(), 'info', 8000);
        }
        return false;
    } finally {
        // Native prompt is single-use; hide until beforeinstallprompt fires again.
        // iOS never reaches here (no deferredPrompt — early return above).
        deferredPrompt = null;
        hideInstallButton();
    }
}

/**
 * Checks if the app is currently installable
 * @returns {boolean} True if install prompt is available
 */
export function isInstallable() {
    return deferredPrompt !== null;
}

/**
 * Attach click handlers for install buttons (idempotent)
 */
function bindInstallButtonClicks() {
    if (clickHandlerBound) {
        return;
    }
    clickHandlerBound = true;

    // Event delegation covers buttons added later / duplicate IDs across slots
    document.addEventListener('click', (e) => {
        if (e.target.closest('#pwaInstallBtn')) {
            e.preventDefault();
            promptInstall().catch(() => {
                // Silently handle errors
            });
        }
    });
}

/**
 * Initializes PWA functionality
 * Should be called during app bootstrap
 * Sets up service worker registration and install prompt handling
 */
export function initPWA() {
    // Check if app is already installed - hide button if so
    if (isStandalone()) {
        hideInstallButton();
        return;
    }

    // Keep button hidden until beforeinstallprompt proves install is available,
    // except on iOS Safari which never fires that event — show it so users can
    // get Add to Home Screen instructions on click.
    hideInstallButton();
    if (isIosSafari()) {
        showInstallButton();
    }
    
    // Register service worker
    registerServiceWorker().catch(() => {
        // Silently handle errors
    });
  
    // Set up install prompt handling
    handleInstallPrompt();
  
    bindInstallButtonClicks();
}
