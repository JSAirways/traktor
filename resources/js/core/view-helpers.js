/**
 * View Helper Functions
 * Utility functions for checking current view state
 */

import { appState } from './state.js';

/**
 * Check if currently in player view
 * @returns {boolean}
 */
export function isInPlayerView() {
    if (!appState || !appState.get) return false;
    return appState.get('currentView') === 'player';
}

/**
 * Check if currently in gallery view
 * @returns {boolean}
 */
export function isInGalleryView() {
    if (!appState || !appState.get) return true; // Default to gallery
    const currentView = appState.get('currentView');
    return currentView === 'gallery' || !currentView; // undefined means gallery
}

/**
 * Get current view
 * @returns {string} 'player' or 'gallery'
 */
export function getCurrentView() {
    if (!appState || !appState.get) return 'gallery';
    return appState.get('currentView') || 'gallery';
}
