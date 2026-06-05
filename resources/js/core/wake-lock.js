/**
 * Wake Lock Manager
 * 
 * Prevents screen from turning off during video playback using the Screen Wake Lock API.
 * Works on Windows, Android, and iOS 16.4+ devices.
 * 
 * The Screen Wake Lock API is a web standard that allows web applications to prevent
 * the device screen from turning off. This is particularly useful for video playback
 * where users may not interact with the screen for extended periods.
 * 
 * Browser Support:
 * - Chrome/Edge 84+ (Windows, Android)
 * - Firefox 89+ (Windows, Android)
 * - Safari 16.4+ (iOS 16.4+, macOS)
 * - PS4 Browser: Not supported (gracefully degrades)
 * 
 * @module core/wake-lock
 */

import { TimingConstants } from './constants.js';

let wakeLock = null;
let isVideoPlaying = false;

/**
 * Request wake lock to prevent screen from turning off
 * 
 * @returns {Promise<boolean>} True if wake lock was acquired, false otherwise
 */
export async function requestWakeLock() {
    // Check if Wake Lock API is supported
    if (!('wakeLock' in navigator)) {
        // API not supported - silently fail (graceful degradation)
        return false;
    }

    // Don't request if already active
    if (wakeLock !== null) {
        return true;
    }

    try {
        // Request screen wake lock
        wakeLock = await navigator.wakeLock.request('screen');
        
        // Handle wake lock release (e.g., user switches tabs, locks device)
        wakeLock.addEventListener('release', () => {
            wakeLock = null;
            
            // If video is still playing, try to re-acquire (iOS may release on some events)
            // Use delay to avoid rapid re-requests
            if (isVideoPlaying) {
                setTimeout(() => {
                    if (isVideoPlaying && wakeLock === null && !document.hidden) {
                        requestWakeLock().catch(() => {
                            // Silently handle - user may have locked device or denied permission
                        });
                    }
                }, TimingConstants.WAKE_LOCK_RETRY_DELAY);
            }
        });

        return true;
    } catch (error) {
        // Common errors:
        // - "NotAllowedError": User denied permission or page not visible
        // - "NotSupportedError": Browser doesn't support wake lock
        // - "AbortError": Wake lock request was aborted
        // Silently handle errors - wake lock is optional functionality
        wakeLock = null;
        return false;
    }
}

/**
 * Release wake lock to allow screen to turn off
 * 
 * @returns {Promise<void>}
 */
export async function releaseWakeLock() {
    if (wakeLock === null) {
        return;
    }

    try {
        await wakeLock.release();
        wakeLock = null;
    } catch (error) {
        // Silently handle errors during release
        wakeLock = null;
    }
}

/**
 * Check if wake lock is currently active
 * 
 * @returns {boolean} True if wake lock is active, false otherwise
 */
export function isWakeLockActive() {
    return wakeLock !== null;
}

/**
 * Set video playing state (used to track if we should maintain wake lock)
 * 
 * @param {boolean} playing - Whether video is currently playing
 */
export function setVideoPlaying(playing) {
    isVideoPlaying = playing;
}

/**
 * Get current video playing state
 * 
 * @returns {boolean} True if video is playing, false otherwise
 */
export function getVideoPlaying() {
    return isVideoPlaying;
}

// Handle page visibility changes
// Release wake lock when page is hidden, re-request when visible (if video is playing)
if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', async () => {
        if (document.hidden) {
            // Page is hidden - release wake lock to save battery
            await releaseWakeLock();
        } else if (isVideoPlaying) {
            // Page is visible again and video is playing - re-request wake lock
            // Small delay to ensure page is fully visible
            setTimeout(() => {
                if (isVideoPlaying && !document.hidden) {
                    requestWakeLock().catch(() => {
                        // Silently handle errors
                    });
                }
            }, TimingConstants.WAKE_LOCK_VISIBILITY_DELAY);
        }
    });
}

