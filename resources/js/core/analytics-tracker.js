/**
 * Analytics Tracker Module
 * Handles tracking of video watch events (event-based, sessions derived server-side)
 */

import { makeRequest } from './utils.js';
import { appState } from './state.js';

class AnalyticsTracker {
    constructor() {
        this.lastPositionUpdate = 0;
        this.positionUpdateInterval = null;
        this.positionUpdateThrottle = 15000; // 15 seconds
        this.isTracking = false;
        this.currentSlug = null;
        this.lastTrackedPosition = 0;
        this.lastTrackedDuration = 0;
        this.currentVideoId = null;
        this.currentVideoPosition = 0;
        this.currentVideoDuration = 0;
        this.currentVideoCompleted = false;
    }

    /**
     * Initialize tracking for a user
     */
    init(slug) {
        this.currentSlug = slug;
        this.isTracking = true;
        this.lastTrackedPosition = 0;
        this.lastTrackedDuration = 0;
    }

    /**
     * Track a watch event
     * Sessions are derived server-side from events (grouped by time gaps)
     */
    async trackEvent(eventType, data = {}) {
        if (!this.isTracking || !this.currentSlug) {
            return;
        }

        // Only track position updates if there's meaningful progress
        if (eventType === 'position_update') {
            const currentPosition = data.position || 0;
            const currentDuration = data.duration || 0;

            // Track if 10% of video or 30 seconds has passed since last update
            const progressThreshold = currentDuration * 0.1; // 10%
            const timeThreshold = 30; // 30 seconds

            const hasSignificantProgress = (currentPosition - this.lastTrackedPosition >= progressThreshold) ||
                                           (currentPosition - this.lastTrackedPosition >= timeThreshold);

            if (!hasSignificantProgress) {
                return;
            }
            this.lastTrackedPosition = currentPosition;
            this.lastTrackedDuration = currentDuration;
        }

        const eventData = {
            event_type: eventType,
            video_id: data.videoId || null,
            playlist_id: data.playlistId || null,
            position: data.position || 0,
            duration: data.duration || null,
            completion_percentage: data.completionPercentage || null,
            slug: this.currentSlug,
        };

        try {
            await makeRequest('/api/analytics/track', {
                method: 'POST',
                body: eventData,
                skipCsrf: false,
            });
        } catch (error) {
            // Log only for debugging - analytics is non-critical
            if (error.status !== 419) { // Don't log CSRF errors repeatedly
                console.debug('[Analytics] Event tracking failed (non-critical):', eventType, error);
            }
        }
    }

    /**
     * Track video started
     */
    async trackVideoStarted(videoId, playlistId = null, duration = null) {
        this.currentVideoId = videoId;
        this.currentVideoPosition = 0;
        this.currentVideoDuration = duration || 0;
        this.currentVideoCompleted = false;
        
        await this.trackEvent('started', {
            videoId,
            playlistId,
            duration,
            position: 0,
            completionPercentage: 0,
        });
    }

    /**
     * Track video paused
     */
    async trackVideoPaused(videoId, position, duration) {
        this.currentVideoPosition = position || 0;
        this.currentVideoDuration = duration || this.currentVideoDuration;
        
        await this.trackEvent('paused', {
            videoId,
            position,
            duration,
        });
    }

    /**
     * Track video resumed
     */
    async trackVideoResumed(videoId, position, duration) {
        this.currentVideoPosition = position || 0;
        this.currentVideoDuration = duration || this.currentVideoDuration;
        
        await this.trackEvent('resumed', {
            videoId,
            position,
            duration,
        });
    }

    /**
     * Track video completed
     */
    async trackVideoCompleted(videoId, duration, playlistId = null) {
        // Prevent duplicate completion events for the same video
        if (this.currentVideoCompleted) {
            return;
        }
        
        this.currentVideoCompleted = true;
        this.currentVideoPosition = duration || this.currentVideoDuration;
        
        await this.trackEvent('completed', {
            videoId,
            playlistId,
            position: duration,
            duration,
            completionPercentage: 100,
        });
    }

    /**
     * Track video abandoned
     */
    async trackVideoAbandoned(videoId, position, duration) {
        await this.trackEvent('abandoned', {
            videoId,
            position,
            duration,
        });
    }

    /**
     * Track position update (throttled - only for significant progress)
     */
    async trackPositionUpdate(videoId, position, duration, playlistId = null) {
        const now = Date.now();
        
        // Update current video state
        this.currentVideoPosition = position || 0;
        this.currentVideoDuration = duration || this.currentVideoDuration;
        
        // Throttle position updates to avoid excessive API calls
        if (now - this.lastPositionUpdate < this.positionUpdateThrottle) {
            return;
        }

        // Only track if there's meaningful progress (at least 10% change or 30 seconds)
        const lastPosition = this.lastTrackedPosition || 0;
        const positionDelta = position - lastPosition;
        const durationDelta = duration > 0 ? (positionDelta / duration) * 100 : 0;
        
        // Skip if less than 10% progress or less than 30 seconds
        if (durationDelta < 10 && positionDelta < 30) {
            return;
        }

        this.lastPositionUpdate = now;
        this.lastTrackedPosition = position;

        await this.trackEvent('position_update', {
            videoId,
            playlistId,
            position,
            duration,
        });
    }

    /**
     * Start position update tracking
     */
    startPositionTracking(videoId, getCurrentTime, getDuration, playlistId = null) {
        this.stopPositionTracking();

        this.positionUpdateInterval = setInterval(() => {
            if (!this.isTracking) {
                return;
            }

            const currentTime = getCurrentTime();
            const duration = getDuration();

            if (currentTime > 0 && duration > 0) {
                this.trackPositionUpdate(videoId, Math.floor(currentTime), Math.floor(duration), playlistId);
            }
        }, this.positionUpdateThrottle);
    }

    /**
     * Stop position update tracking
     */
    stopPositionTracking() {
        if (this.positionUpdateInterval) {
            clearInterval(this.positionUpdateInterval);
            this.positionUpdateInterval = null;
        }
    }

    /**
     * Track abandoned video (when user leaves before completion)
     */
    async trackAbandonedIfNeeded() {
        // Only track abandoned if:
        // 1. There's a current video being tracked
        // 2. Video hasn't been completed
        // 3. Video was actually watched (position > 0 or duration > 0)
        if (this.currentVideoId && 
            !this.currentVideoCompleted && 
            (this.currentVideoPosition > 0 || this.currentVideoDuration > 0)) {
            
            const completionPercentage = this.currentVideoDuration > 0 
                ? Math.min(100, (this.currentVideoPosition / this.currentVideoDuration) * 100)
                : 0;
            
            // Only track as abandoned if watched less than 95% (completed videos are tracked separately)
            if (completionPercentage < 95) {
                await this.trackVideoAbandoned(
                    this.currentVideoId,
                    this.currentVideoPosition,
                    this.currentVideoDuration
                );
            }
        }
    }

    /**
     * Cleanup tracking
     */
    cleanup() {
        // Track abandoned before cleanup if video wasn't completed
        this.trackAbandonedIfNeeded();
        
        this.stopPositionTracking();
        this.isTracking = false;
        this.currentSlug = null;
        this.lastTrackedPosition = 0;
        this.lastTrackedDuration = 0;
        this.currentVideoId = null;
        this.currentVideoPosition = 0;
        this.currentVideoDuration = 0;
        this.currentVideoCompleted = false;
    }
}

// Create singleton instance
export const analyticsTracker = new AnalyticsTracker();

