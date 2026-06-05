/**
 * PWA Installer Module
 * Handles service worker registration and PWA installation prompts
 */

import { eventEmitter } from './events.js';

let deferredPrompt = null;

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
 * Handles install prompt events and manages install button visibility
 * Uses eventEmitter for cross-module communication
 */
export function handleInstallPrompt() {
    // Check if app is already installed
    if (window.matchMedia('(display-mode: standalone)').matches) {
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
        
        // Show install button by toggling class (following rulebook: no DOM manipulation)
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
 * @returns {Promise<boolean>} True if user accepted, false if dismissed
 */
export async function promptInstall() {
    if (!deferredPrompt) {
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
        // Silently handle install prompt errors
        return false;
    } finally {
        // Clean up deferred prompt
        deferredPrompt = null;
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
 * Initializes PWA functionality
 * Should be called during app bootstrap
 * Sets up service worker registration and install prompt handling
 */
export function initPWA() {
    // Check if app is already installed - hide button if so
    if (window.matchMedia('(display-mode: standalone)').matches) {
        hideInstallButton();
        return;
    }
    
    // Register service worker
    registerServiceWorker().catch(() => {
        // Silently handle errors
    });
  
    // Set up install prompt handling
    handleInstallPrompt();
  
    // Set up install button click handler
    // Use event delegation to handle buttons that may be added dynamically
    document.addEventListener('click', (e) => {
        if (e.target.closest('#pwaInstallBtn')) {
            e.preventDefault();
            promptInstall().catch(() => {
                // Silently handle errors
            });
        }
    });
    
    // Also attach directly to existing buttons for immediate functionality
    const installBtns = document.querySelectorAll('#pwaInstallBtn');
    installBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            promptInstall().catch(() => {
                // Silently handle errors
            });
        });
    });
    
    // Show button by default - it will be hidden if app is already installed
    // or if beforeinstallprompt never fires (browser doesn't support/allow installation)
    // Wait a bit to see if beforeinstallprompt fires, then show if it hasn't been hidden
    setTimeout(() => {
        // Only show if we haven't received a beforeinstallprompt event yet
        // and the app is not installed
        if (!deferredPrompt && !window.matchMedia('(display-mode: standalone)').matches) {
            // Check if button is still hidden - if so, show it
            // This handles cases where beforeinstallprompt hasn't fired yet
            // or won't fire (browser doesn't support it)
            const installBtns = document.querySelectorAll('#pwaInstallBtn');
            installBtns.forEach(btn => {
                if (btn.style.display === 'none' || btn.classList.contains('d-none')) {
                    // Show button - let user try to install
                    showInstallButton();
                }
            });
        }
    }, 1000); // Wait 1 second for beforeinstallprompt to potentially fire
}
