/**
 * Playlist State Manager
 * Single source of truth for playlist state management
 * Enforces view-aware rules and prevents invalid state transitions
 */

import { appState } from '../core/state.js';

/**
 * Playlist State Manager
 * Centralized management of playlist state with view-aware rules
 */
export class PlaylistStateManager {
    /**
     * Check if currently in player view
     */
    isInPlayerView() {
        if (!appState || !appState.get) return false;
        return appState.get('currentView') === 'player';
    }
    
    /**
     * Check if currently in gallery view
     */
    isInGalleryView() {
        if (!appState || !appState.get) return true; // Default to gallery
        const currentView = appState.get('currentView');
        return currentView === 'gallery' || !currentView; // undefined means gallery
    }
    
    /**
     * Set playlist state
     * @param {number} playlistId - Playlist ID
     * @param {Array} videos - Array of video objects
     * @param {number} index - Current video index (default: -1)
     */
    setPlaylist(playlistId, videos, index = -1) {
        if (!appState || !appState.setState) {
            return false;
        }
        
        // Validate inputs
        if (!playlistId || !videos || !Array.isArray(videos)) {
            return false;
        }
        
        // Validate index
        if (index < -1 || index >= videos.length) {
            index = -1; // Reset to invalid index
        }
        
        appState.setState({
            currentPlaylistId: parseInt(playlistId, 10),
            currentPlaylistVideos: videos,
            currentVideoIndex: parseInt(index, 10)
        });
        
        return true;
    }
    
    /**
     * Clear playlist state
     * Only clears if not in player view (to prevent interrupting playback)
     * @param {boolean} force - Force clear even in player view (default: false)
     */
    clearPlaylist(force = false) {
        if (!appState || !appState.setState) {
            return false;
        }
        
        // Don't clear if in player view (unless forced)
        if (!force && this.isInPlayerView()) {
            return false;
        }
        
        appState.setState({
            currentPlaylistId: null,
            currentPlaylistVideos: [],
            currentVideoIndex: -1
        });
        
        return true;
    }
    
    /**
     * Get current playlist ID
     */
    getPlaylistId() {
        if (!appState || !appState.get) return null;
        return appState.get('currentPlaylistId');
    }
    
    /**
     * Get current playlist videos
     */
    getVideos() {
        if (!appState || !appState.get) return null;
        return appState.get('currentPlaylistVideos');
    }
    
    /**
     * Get current video index
     */
    getCurrentIndex() {
        if (!appState || !appState.get) return -1;
        return appState.get('currentVideoIndex');
    }
    
    /**
     * Increment video index
     * @returns {boolean} True if successful, false if at end
     */
    incrementIndex() {
        if (!appState || !appState.get || !appState.set) return false;
        
        const videos = this.getVideos();
        const currentIndex = this.getCurrentIndex();
        
        if (!videos || videos.length === 0 || currentIndex < 0) {
            return false;
        }
        
        const nextIndex = currentIndex + 1;
        if (nextIndex >= videos.length) {
            return false; // At end
        }
        
        appState.set('currentVideoIndex', nextIndex);
        return true;
    }
    
    /**
     * Decrement video index
     * @returns {boolean} True if successful, false if at start
     */
    decrementIndex() {
        if (!appState || !appState.get || !appState.set) return false;
        
        const videos = this.getVideos();
        const currentIndex = this.getCurrentIndex();
        
        if (!videos || videos.length === 0 || currentIndex <= 0) {
            return false; // At start or invalid
        }
        
        const prevIndex = currentIndex - 1;
        appState.set('currentVideoIndex', prevIndex);
        return true;
    }
    
    /**
     * Set video index
     * @param {number} index - Index to set
     * @returns {boolean} True if successful
     */
    setIndex(index) {
        if (!appState || !appState.get || !appState.set) return false;
        
        const videos = this.getVideos();
        if (!videos || videos.length === 0) {
            return false;
        }
        
        const parsedIndex = parseInt(index, 10);
        if (parsedIndex < 0 || parsedIndex >= videos.length) {
            return false;
        }
        
        appState.set('currentVideoIndex', parsedIndex);
        return true;
    }
    
    /**
     * Check if playlist state is valid
     */
    isValid() {
        const playlistId = this.getPlaylistId();
        const videos = this.getVideos();
        const index = this.getCurrentIndex();
        
        return playlistId !== null && 
               videos !== null && 
               Array.isArray(videos) && 
               videos.length > 0 && 
               index >= 0 && 
               index < videos.length;
    }
    
    /**
     * Get current video from playlist
     */
    getCurrentVideo() {
        const videos = this.getVideos();
        const index = this.getCurrentIndex();
        
        if (!videos || videos.length === 0 || index < 0 || index >= videos.length) {
            return null;
        }
        
        return videos[index];
    }
    
    /**
     * Get next video from playlist
     */
    getNextVideo() {
        const videos = this.getVideos();
        const index = this.getCurrentIndex();
        
        if (!videos || videos.length === 0 || index < 0) {
            return null;
        }
        
        const nextIndex = index + 1;
        if (nextIndex >= videos.length) {
            return null; // At end
        }
        
        return videos[nextIndex];
    }
    
    /**
     * Get previous video from playlist
     */
    getPrevVideo() {
        const videos = this.getVideos();
        const index = this.getCurrentIndex();
        
        if (!videos || videos.length === 0 || index <= 0) {
            return null; // At start or invalid
        }
        
        const prevIndex = index - 1;
        return videos[prevIndex];
    }
    
    /**
     * Check if at first video
     */
    isAtFirst() {
        return this.getCurrentIndex() === 0;
    }
    
    /**
     * Check if at last video
     */
    isAtLast() {
        const videos = this.getVideos();
        const index = this.getCurrentIndex();
        
        if (!videos || videos.length === 0 || index < 0) {
            return false;
        }
        
        return index >= videos.length - 1;
    }
}

// Create and export singleton instance
export const playlistStateManager = new PlaylistStateManager();

// Also attach to global namespace for backward compatibility during transition
if (typeof window !== 'undefined') {
    if (!window.Traktor) {
        window.Traktor = {};
    }
    if (!window.Traktor.Modules) {
        window.Traktor.Modules = {};
    }
    window.Traktor.Modules.PlaylistStateManager = PlaylistStateManager;
    window.Traktor.Modules.playlistStateManager = playlistStateManager;
}
