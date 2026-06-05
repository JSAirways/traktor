/**
 * Simple event emitter for inter-module communication
 */

/**
 * Event emitter class for pub/sub pattern
 */
class EventEmitter {
    constructor() {
        this.events = {};
    }
    
    /**
     * Subscribe to an event
     * @param {string} eventName - Name of the event
     * @param {Function} callback - Function to call when event is emitted
     * @returns {Function} Unsubscribe function
     */
    on(eventName, callback) {
        if (!this.events[eventName]) {
            this.events[eventName] = [];
        }
        this.events[eventName].push(callback);
        
        // Return unsubscribe function
        return () => {
            this.off(eventName, callback);
        };
    }
    
    /**
     * Subscribe to an event once (fires once then automatically unsubscribes)
     * @param {string} eventName - Name of the event
     * @param {Function} callback - Function to call when event is emitted
     * @returns {Function} Unsubscribe function
     */
    once(eventName, callback) {
        // Create a wrapper function that calls the callback and then unsubscribes
        const onceWrapper = (data) => {
            callback(data);
            this.off(eventName, onceWrapper);
        };
        // Subscribe with the wrapper
        return this.on(eventName, onceWrapper);
    }
    
    /**
     * Unsubscribe from an event
     * @param {string} eventName - Name of the event
     * @param {Function} callback - Callback function to remove
     */
    off(eventName, callback) {
        if (!this.events[eventName]) return;
        
        this.events[eventName] = this.events[eventName].filter(
            cb => cb !== callback
        );
        
        if (this.events[eventName].length === 0) {
            delete this.events[eventName];
        }
    }
    
    /**
     * Emit an event
     * @param {string} eventName - Name of the event
     * @param {*} data - Data to pass to event handlers
     */
    emit(eventName, data = null) {
        if (!this.events[eventName]) return;
        
        this.events[eventName].forEach(callback => {
            try {
                callback(data);
            } catch (error) {
                console.error(`Error in event handler for ${eventName}:`, error);
            }
        });
    }
    
    /**
     * Remove all listeners for an event
     * @param {string} eventName - Name of the event
     */
    removeAllListeners(eventName) {
        delete this.events[eventName];
    }
    
    /**
     * Clear all events
     */
    clear() {
        this.events = {};
    }
}

// Create and export singleton instance
export const eventEmitter = new EventEmitter();
