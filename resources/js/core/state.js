/**
 * Centralized state management
 * Provides event-driven state updates and reactive subscriptions
 */

/**
 * Application state management class
 */
class AppState {
    constructor() {
        this.state = {
            // Player state
            player: null,
            playerReady: false,
            isPausedByScript: false,
            isVideoPaused: false,
            
            // Video state
            currentVideoId: null,
            currentVideoIndex: -1,
            
            // Playlist state
            currentPlaylistId: null,
            currentPlaylistVideos: [],
            
            // View state
            isFullscreen: false,
            
            // Navbar state
            navbarPlayerViewMode: false,
            navbarHidden: false,
            
            // Slug (URL-friendly identifier)
            currentSlug: null,
        };
        
        this.subscribers = [];
    }
    
    /**
     * Get current state (returns a copy)
     */
    getState() {
        return { ...this.state };
    }
    
    /**
     * Get specific state value
     */
    get(key) {
        return this.state[key];
    }
    
    /**
     * Set state (supports partial updates)
     */
    setState(updates) {
        const prevState = { ...this.state };
        this.state = { ...this.state, ...updates };
        
        // Notify subscribers
        this.subscribers.forEach(callback => {
            callback(this.state, prevState);
        });
    }
    
    /**
     * Set single key
     */
    set(key, value) {
        this.setState({ [key]: value });
    }
    
    /**
     * Subscribe to state changes
     * @param {Function} callback - Function called with (newState, prevState)
     * @returns {Function} Unsubscribe function
     */
    subscribe(callback) {
        this.subscribers.push(callback);
        
        // Return unsubscribe function
        return () => {
            this.subscribers = this.subscribers.filter(cb => cb !== callback);
        };
    }
}

// Create and export singleton instance
export const appState = new AppState();
