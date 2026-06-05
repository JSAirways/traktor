/**
 * Video Player Module
 * Handles YouTube player initialization and control
 */

import { appState } from '../core/state.js';
import { eventEmitter } from '../core/events.js';
import { requestWakeLock, releaseWakeLock, setVideoPlaying } from '../core/wake-lock.js';

// YouTube Player API setup - load with defer for better performance
// Only load if not already loaded
if (typeof window !== 'undefined' && (!window.YT || !window.YT.Player)) {
    const tag = document.createElement('script');
    tag.src = "https://www.youtube.com/iframe_api";
    tag.defer = true;
    tag.async = true;
    const firstScriptTag = document.getElementsByTagName('script')[0];
    if (firstScriptTag?.parentNode) {
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
    }
}

export class VideoPlayer {
    constructor() {
        this.player = null;
        this.playerReady = false;
        this.isPausedByScript = false;
    }
    
    // Initialize YouTube player (called by global onYouTubeIframeAPIReady)
    initialize() {
        const playerElement = document.getElementById('player');
        if (!playerElement) {
            return;
        }
        
        // Check if player is already initialized
        if (this.player) {
            return;
        }
        
        // Check if player container is visible - if not, wait for it to become visible
        // YouTube API can initialize hidden elements, but we should ensure container is visible
        const playerContainer = playerElement.closest?.('.player-view-container');
        const isContainerVisible = !playerContainer || !playerContainer.classList.contains('d-none');
        
        // If container is hidden, don't initialize yet - wait for view to become visible
        if (!isContainerVisible) {
            // Wait for container to become visible via event
            if (eventEmitter?.on) {
                eventEmitter.once('view:player-shown', () => {
                    // Retry initialization when view becomes visible
                    if (!this.player) {
                        this.initialize();
                    }
                });
            }
            return;
        }
        
        // Get current origin to prevent postMessage origin errors
        // This tells YouTube API what origin to expect for postMessage communication
        const origin = window.location.origin;
        
        try {
            this.player = new YT.Player('player', {
                height: '100%',
                width: '100%',
                // Set origin to current origin to prevent postMessage errors
                // This is a top-level option, not in playerVars
                origin: origin,
                playerVars: {
                    controls: 0, // Hide all YouTube controls - we'll use custom controls
                    modestbranding: 1,
                    rel: 0,
                    showinfo: 0,
                    disablekb: 1,
                    fs: 0,
                    iv_load_policy: 3,
                    playsinline: 1,
                    autohide: 1,
                    enablejsapi: 1, // Explicitly enable JavaScript API
                    autoplay: 1 // Try autoplay (may be blocked by browser)
                },
                events: {
                    'onReady': (event) => {
                        this.onReady(event);
                    },
                    'onStateChange': (event) => {
                        this.onStateChange(event);
                    },
                    'onError': (event) => {
                        console.error('[VideoPlayer] YouTube API error:', event.data);
                    }
                }
            });
        } catch (error) {
            console.error('[VideoPlayer] Failed to initialize player:', error);
            throw error;
        }
    }
    
    onReady(event) {
        this.player.setPlaybackQuality('hd1080');
        event.target.stopVideo();
        this.playerReady = true;
        
        // Update state
        if (appState?.setState) {
            appState.setState({
                player: this.player,
                playerReady: true
            });
        }
        
        // Emit event
        if (eventEmitter?.emit) {
            eventEmitter.emit('player:ready', { player: this.player });
        }
    }
    
    onStateChange(event) {
        const state = event.data;
        
        // Update state based on YouTube player state
        if (state === YT.PlayerState.PLAYING) {
            // Request wake lock when video starts playing
            setVideoPlaying(true);
            requestWakeLock().catch(() => {
                // Silently handle errors - wake lock is optional functionality
            });
            
            if (appState?.set) {
                appState.set('isVideoPaused', false);
            }
            if (eventEmitter?.emit) {
                eventEmitter.emit('video:play', { player: this.player });
            }
        } else if (state === YT.PlayerState.PAUSED || state === YT.PlayerState.CUED) {
            // Release wake lock when video is paused
            setVideoPlaying(false);
            releaseWakeLock().catch(() => {
                // Silently handle errors
            });
            
            if (appState?.set) {
                appState.set('isVideoPaused', true);
            }
            if (eventEmitter?.emit) {
                eventEmitter.emit('video:pause', { player: this.player });
            }
        } else if (state === YT.PlayerState.ENDED) {
            // Release wake lock when video ends
            setVideoPlaying(false);
            releaseWakeLock().catch(() => {
                // Silently handle errors
            });
            
            // Emit video:ended event - playlist module or player page will handle navigation
            if (eventEmitter?.emit) {
                eventEmitter.emit('video:ended', { player: this.player });
            } else {
                console.error('[VideoPlayer] Event emitter not available!');
            }
            // Also emit statechange so controls can update
            if (eventEmitter?.emit) {
                eventEmitter.emit('video:statechange', { state: state, player: this.player });
            }
        }
        
        // Emit statechange for all states (needed for controls UI updates including cat GIF)
        if (eventEmitter?.emit) {
            eventEmitter.emit('video:statechange', { state: state, player: this.player });
        }
    }
    
    // Load and play a video
    loadVideo(videoId, autoplay) {
        if (!this.playerReady || !this.player) {
            return;
        }
        
        try {
            // Load the video
            this.player.loadVideoById(videoId);
            
            // Try to play if autoplay requested
            // Note: Browser may block autoplay, so this may not work
            if (autoplay) {
                // Wait for video to load, then try to play
                // Use requestAnimationFrame for better timing
                const attemptPlay = () => {
                    try {
                        if (this.player?.playVideo) {
                            this.player.playVideo();
                        }
                    } catch (e) {
                        // Autoplay blocked - user will need to click play
                        // Emit event so controls can show play button
                        if (eventEmitter?.emit) {
                            eventEmitter.emit('video:autoplay-blocked', { videoId: videoId });
                        }
                    }
                };
                
                // Try immediately, then again after a short delay
                if (window.requestAnimationFrame) {
                    window.requestAnimationFrame(() => {
                        attemptPlay();
                        setTimeout(attemptPlay, 500);
                    });
                } else {
                    setTimeout(attemptPlay, 500);
                }
            }
            
            if (appState?.set) {
                appState.set('currentVideoId', videoId);
            }
        } catch (error) {
            if (eventEmitter?.emit) {
                eventEmitter.emit('video:error', { error: error, videoId: videoId });
            }
        }
    }
    
    // Play video
    play() {
        if (!this.player) return;
        
        try {
            this.player.playVideo();
        } catch (error) {
            // Silently handle play errors
        }
    }
    
    // Pause video
    pause() {
        if (!this.player) return;
        
        try {
            this.player.pauseVideo();
            this.isPausedByScript = true;
        } catch (error) {
            // Silently handle pause errors
        }
    }
    
    // Toggle play/pause
    togglePlayPause() {
        if (!this.player) return;
        
        try {
            const state = this.player.getPlayerState();
            if (state === YT.PlayerState.PLAYING) {
                this.pause();
            } else {
                this.play();
            }
        } catch (error) {
            // Silently handle toggle errors
        }
    }
    
    // Stop video
    stop() {
        if (!this.player) return;
        
        try {
            this.player.stopVideo();
        } catch (error) {
            // Silently handle stop errors
        }
    }
    
    // Seek to specific time
    seekTo(seconds) {
        if (!this.player) return;
        
        try {
            this.player.seekTo(seconds, true);
        } catch (error) {
            // Silently handle seek errors
        }
    }
    
    // Get current time
    getCurrentTime() {
        if (!this.player) return 0;
        
        try {
            return this.player.getCurrentTime();
        } catch (error) {
            return 0;
        }
    }
    
    // Get duration
    getDuration() {
        if (!this.player) return 0;
        
        try {
            return this.player.getDuration();
        } catch (error) {
            return 0;
        }
    }
    
    // Get player state
    getPlayerState() {
        if (!this.player) return null;
        
        try {
            return this.player.getPlayerState();
        } catch (error) {
            return null;
        }
    }
    
    // Check if player is ready
    isReady() {
        return this.playerReady && this.player !== null;
    }
    
    // Get player instance
    getPlayer() {
        return this.player;
    }
    
    // Destroy player instance (for cleanup when switching views)
    destroy() {
        if (this.player) {
            try {
                if (this.player.destroy) {
                    this.player.destroy();
                }
            } catch (e) {
                // Ignore errors during destruction
            }
            this.player = null;
            this.playerReady = false;
        }
        
        // Release wake lock when player is destroyed
        setVideoPlaying(false);
        releaseWakeLock().catch(() => {
            // Silently handle errors
        });
    }
}

// Create singleton instance and export
export const videoPlayer = new VideoPlayer();

// Also attach to global namespace for backward compatibility during transition
if (typeof window !== 'undefined') {
    if (!window.Traktor) {
        window.Traktor = {};
    }
    if (!window.Traktor.Modules) {
        window.Traktor.Modules = {};
    }
    window.Traktor.Modules.VideoPlayer = VideoPlayer;
    window.Traktor.Modules.videoPlayer = videoPlayer;
    
    // Global callback for YouTube API (must be on window)
    window.onYouTubeIframeAPIReady = () => {
        videoPlayer.initialize();
    };
    
    // Check if YouTube API is already loaded
    if (window.YT && window.YT.Player) {
        videoPlayer.initialize();
    }
}
