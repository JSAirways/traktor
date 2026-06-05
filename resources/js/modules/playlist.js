/**
 * Playlist Module
 * Handles playlist playback and navigation
 */

import { appState } from '../core/state.js';
import { eventEmitter } from '../core/events.js';
import { toggleElementVisibility } from '../core/utils.js';

// Import other modules dynamically to avoid circular dependencies
function getVideoPlayer() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.videoPlayer;
    }
    return null;
}

function getPlaylistStateManager() {
    if (typeof window !== 'undefined' && window.Traktor && window.Traktor.Modules) {
        return window.Traktor.Modules.playlistStateManager;
    }
    return null;
}

export class Playlist {
    constructor() {
        this.init();
    }
    
    init() {
        // Listen for playlist loaded event
        if (eventEmitter?.on) {
            eventEmitter.on('playlist:loaded', (data) => {
                // Get current state using PlaylistStateManager if available
                const playlistStateManager = getPlaylistStateManager();
                let currentPlaylistId, currentVideoIndex, currentPlaylistVideos;
                if (playlistStateManager) {
                    currentPlaylistId = playlistStateManager.getPlaylistId();
                    currentVideoIndex = playlistStateManager.getCurrentIndex();
                    currentPlaylistVideos = playlistStateManager.getVideos();
                } else {
                    currentPlaylistId = appState?.get('currentPlaylistId');
                    currentVideoIndex = appState?.get('currentVideoIndex') ?? -1;
                    currentPlaylistVideos = appState?.get('currentPlaylistVideos');
                }
                const currentView = appState?.get('currentView');
                
                // If we already have playlist state with videos and we're viewing the same playlist,
                // don't reset it - this prevents clearing state when switching to player view
                // Check even if currentView is undefined (might be transitioning to player view)
                if (currentPlaylistId === data.playlistId && 
                    currentPlaylistVideos && 
                    currentPlaylistVideos.length > 0) {
                    // If we have a valid index, definitely don't reset
                    if (currentVideoIndex >= 0) {
                        return; // Don't reset the state
                    }
                    // If index is -1 but we have videos, only reset if we're definitely in gallery view
                    // (not transitioning to player view)
                    if (currentVideoIndex === -1 && currentView !== 'gallery') {
                        return; // Don't reset during transition
                    }
                }
                
                // If we're in player view with the same playlist but no index yet, preserve videos but set index to 0
                if (currentView === 'player' && currentPlaylistId === data.playlistId) {
                    this.setPlaylist(data.playlistId, data.videos, 0);
                } else if (currentView === 'player') {
                    // In player view but different playlist - don't reset!
                    return;
                } else {
                    // Gallery view - set playlist normally
                    this.setPlaylist(data.playlistId, data.videos);
                }
            });
            
            // Listen for video ended to auto-play next
            eventEmitter.on('video:ended', () => {
                this.handleVideoEnded();
            });
        }
    }
    
    // Set current playlist
    setPlaylist(playlistId, videos, initialIndex = -1) {
        const playlistStateManager = getPlaylistStateManager();
        
        // Use PlaylistStateManager if available, otherwise fallback to direct appState
        if (playlistStateManager?.setPlaylist) {
            playlistStateManager.setPlaylist(playlistId, videos, initialIndex);
        } else if (appState?.setState) {
            // Fallback for backwards compatibility
            appState.setState({
                currentPlaylistId: playlistId,
                currentPlaylistVideos: videos,
                currentVideoIndex: initialIndex
            });
        }
        
        this.updateNavbar();
        // Emit event to notify view-manager of playlist state change
        if (eventEmitter?.emit) {
            eventEmitter.emit('playlist:statechanged', { playlistId: playlistId, videos: videos });
        }
    }
    
    // Play next video in playlist
    next() {
        const playlistStateManager = getPlaylistStateManager();
        const videoPlayer = getVideoPlayer();
        
        // Use PlaylistStateManager if available
        if (playlistStateManager) {
            if (!playlistStateManager.incrementIndex()) {
                return false; // At end
            }
            
            const nextVideo = playlistStateManager.getCurrentVideo();
            if (!nextVideo) {
                return false;
            }
            
            // Emit event to update URL (player page handles this)
            const nextIndex = playlistStateManager.getCurrentIndex();
            if (eventEmitter?.emit) {
                eventEmitter.emit('playlist:update-url', { index: nextIndex });
            }
            
            if (videoPlayer?.loadVideo) {
                videoPlayer.loadVideo(nextVideo.video_id);
            }
            
            // Ensure video starts playing
            setTimeout(() => {
                if (videoPlayer?.isReady?.()) {
                    if (videoPlayer.play) {
                        videoPlayer.play();
                    }
                }
            }, 100);
            
            setTimeout(() => {
                this.updateNavbar();
            }, 100); // Delay for state update
            return true;
        }
        
        // Fallback to direct appState access
        const videos = appState?.get('currentPlaylistVideos');
        const currentIndex = appState?.get('currentVideoIndex') ?? -1;
        
        if (!videos || videos.length === 0) return false;
        
        const nextIndex = currentIndex + 1;
        if (nextIndex < videos.length) {
            if (appState?.set) {
                appState.set('currentVideoIndex', nextIndex);
            }
            const nextVideo = videos[nextIndex];
            
            // Emit event to update URL (player page handles this)
            if (eventEmitter?.emit) {
                eventEmitter.emit('playlist:update-url', { index: nextIndex });
            }
            
            if (videoPlayer?.loadVideo) {
                videoPlayer.loadVideo(nextVideo.video_id);
            }
            
            // Ensure video starts playing
            setTimeout(() => {
                if (videoPlayer?.isReady?.()) {
                    if (videoPlayer.play) {
                        videoPlayer.play();
                    }
                }
            }, 100);
            
            setTimeout(() => {
                this.updateNavbar();
            }, 100); // Delay for state update
            return true;
        }
        
        return false;
    }
    
    // Play previous video in playlist
    prev() {
        const playlistStateManager = getPlaylistStateManager();
        const videoPlayer = getVideoPlayer();
        
        // Use PlaylistStateManager if available
        if (playlistStateManager) {
            if (!playlistStateManager.decrementIndex()) {
                return false; // At start
            }
            
            const prevVideo = playlistStateManager.getCurrentVideo();
            if (!prevVideo) {
                return false;
            }
            
            // Emit event to update URL (player page handles this)
            const prevIndex = playlistStateManager.getCurrentIndex();
            if (eventEmitter?.emit) {
                eventEmitter.emit('playlist:update-url', { index: prevIndex });
            }
            
            if (videoPlayer?.loadVideo) {
                videoPlayer.loadVideo(prevVideo.video_id);
            }
            
            // Ensure video starts playing
            setTimeout(() => {
                if (videoPlayer?.isReady?.()) {
                    if (videoPlayer.play) {
                        videoPlayer.play();
                    }
                }
            }, 100);
            
            setTimeout(() => {
                this.updateNavbar();
            }, 100); // Delay for state update
            return true;
        }
        
        // Fallback to direct appState access
        const videos = appState?.get('currentPlaylistVideos');
        const currentIndex = appState?.get('currentVideoIndex') ?? -1;
        
        if (!videos || videos.length === 0) return false;
        
        const prevIndex = currentIndex - 1;
        if (prevIndex >= 0) {
            if (appState?.set) {
                appState.set('currentVideoIndex', prevIndex);
            }
            const prevVideo = videos[prevIndex];
            
            // Emit event to update URL (player page handles this)
            if (eventEmitter?.emit) {
                eventEmitter.emit('playlist:update-url', { index: prevIndex });
            }
            
            if (videoPlayer?.loadVideo) {
                videoPlayer.loadVideo(prevVideo.video_id);
            }
            
            // Ensure video starts playing
            setTimeout(() => {
                if (videoPlayer?.isReady?.()) {
                    if (videoPlayer.play) {
                        videoPlayer.play();
                    }
                }
            }, 100);
            
            setTimeout(() => {
                this.updateNavbar();
            }, 100); // Delay for state update
            return true;
        }
        
        return false;
    }
    
    // Handle video ended - auto-play next if in playlist
    handleVideoEnded() {
        const playlistStateManager = getPlaylistStateManager();
        const videoPlayer = getVideoPlayer();
        
        // Use PlaylistStateManager if available
        if (playlistStateManager?.isValid()) {
            const playlistId = playlistStateManager.getPlaylistId();
            const currentIndex = playlistStateManager.getCurrentIndex();
            
            // Check if there's a next video
            const nextVideo = playlistStateManager.getNextVideo();
            if (nextVideo) {
                // Play next video in playlist
                
                // Increment index
                playlistStateManager.incrementIndex();
                
                // Update URL via History API (player page handles this)
                const nextIndex = playlistStateManager.getCurrentIndex();
                if (eventEmitter?.emit) {
                    eventEmitter.emit('playlist:update-url', { index: nextIndex });
                }
                
                // Load the next video (loadVideoById auto-plays by default)
                if (videoPlayer?.loadVideo) {
                    videoPlayer.loadVideo(nextVideo.video_id);
                }
                
                // Ensure video starts playing (small delay to let video load)
                setTimeout(() => {
                    if (videoPlayer?.isReady?.()) {
                        if (videoPlayer.play) {
                            videoPlayer.play();
                        }
                    }
                }, 100);
                
                this.updateNavbar();
            } else {
                // Last video in playlist - navigate back to gallery
                // Stop the current video first
                if (videoPlayer?.stop) {
                    videoPlayer.stop();
                }
                
                // Emit event to navigate back to gallery (player page handles this)
                if (eventEmitter?.emit) {
                    eventEmitter.emit('playlist:ended');
                }
            }
        } else {
            // Fallback to direct appState access
            const playlistId = appState?.get('currentPlaylistId');
            const videos = appState?.get('currentPlaylistVideos');
            const currentIndex = appState?.get('currentVideoIndex') ?? -1;
            
            if (playlistId && videos && videos.length > 0 && currentIndex >= 0) {
                const nextIndex = currentIndex + 1;
                if (nextIndex < videos.length) {
                    // Play next video in playlist
                    if (appState?.set) {
                        appState.set('currentVideoIndex', nextIndex);
                    }
                    const nextVideo = videos[nextIndex];
                    
                    // Update URL via History API (player page handles this)
                    if (eventEmitter?.emit) {
                        eventEmitter.emit('playlist:update-url', { index: nextIndex });
                    }
                    
                    // Load the next video
                    if (videoPlayer?.loadVideo) {
                        videoPlayer.loadVideo(nextVideo.video_id);
                    }
                    
                    // Ensure video starts playing
                    setTimeout(() => {
                        if (videoPlayer?.isReady?.()) {
                            if (videoPlayer.play) {
                                videoPlayer.play();
                            }
                        }
                    }, 100);
                    
                    this.updateNavbar();
                } else {
                    // Last video - navigate back to gallery
                    if (videoPlayer?.stop) {
                        videoPlayer.stop();
                    }
                    if (eventEmitter?.emit) {
                        eventEmitter.emit('playlist:ended');
                    }
                }
            } else {
                // Regular video ended - navigate back to gallery
                if (eventEmitter?.emit) {
                    eventEmitter.emit('video:ended-single');
                }
            }
        }
    }
    
    // Update navbar for playlist context
    updateNavbar() {
        const playlistStateManager = getPlaylistStateManager();
        
        // Use PlaylistStateManager if available
        let videos, currentIndex;
        if (playlistStateManager?.isValid()) {
            videos = playlistStateManager.getVideos();
            currentIndex = playlistStateManager.getCurrentIndex();
        } else {
            // Fallback to direct appState access
            videos = appState?.get('currentPlaylistVideos');
            currentIndex = appState?.get('currentVideoIndex') ?? -1;
        }
        
        if (videos && videos.length > 0 && currentIndex >= 0) {
            const totalVideos = videos.length;
            const currentNumber = currentIndex + 1;
            
            const counter = document.getElementById('playlistCounter');
            if (counter) {
                // Use textContent for safety, but preserve span structure if needed
                // Since we need a span, we'll create it properly
                const currentSpan = counter.querySelector('.current-video-number');
                if (currentSpan) {
                    currentSpan.textContent = currentNumber;
                    // Update the rest of the text
                    const textNode = counter.childNodes[1];
                    if (textNode && textNode.nodeType === 3) { // Node.TEXT_NODE = 3
                        textNode.textContent = ` / ${totalVideos}`;
                    } else {
                        // Fallback: create structure properly
                        counter.innerHTML = `<span class="current-video-number">${currentNumber}</span> / ${totalVideos}`;
                    }
                } else {
                    // First time setup - need to create structure
                    counter.innerHTML = `<span class="current-video-number">${currentNumber}</span> / ${totalVideos}`;
                }
            }
            
            // Show playlist navigation buttons
            const navButtons = document.getElementById('playlistNavButtons');
            if (navButtons) {
                navButtons.classList.remove('d-none');
            }
            
            // Also try toggleElementVisibility if available
            if (toggleElementVisibility) {
                toggleElementVisibility('playlistNavButtons', true);
            }
            
            // Update button states
            const prevBtn = document.getElementById('prevVideoBtn');
            const nextBtn = document.getElementById('nextVideoBtn');
            
            // Use PlaylistStateManager for button states if available
            const isAtFirst = playlistStateManager ? playlistStateManager.isAtFirst() : (currentIndex === 0);
            const isAtLast = playlistStateManager ? playlistStateManager.isAtLast() : (currentIndex >= totalVideos - 1);
            
            if (prevBtn) {
                prevBtn.disabled = isAtFirst;
                prevBtn.style.opacity = isAtFirst ? '0.5' : '1';
            }
            
            if (nextBtn) {
                nextBtn.disabled = isAtLast;
                nextBtn.style.opacity = isAtLast ? '0.5' : '1';
            }
        } else {
            const navButtons = document.getElementById('playlistNavButtons');
            if (navButtons) {
                navButtons.classList.add('d-none');
            }
            if (toggleElementVisibility) {
                toggleElementVisibility('playlistNavButtons', false);
            }
        }
    }
    
    // Get current video index
    getCurrentIndex() {
        const playlistStateManager = getPlaylistStateManager();
        if (playlistStateManager) {
            return playlistStateManager.getCurrentIndex();
        }
        return appState?.get('currentVideoIndex') ?? -1;
    }
    
    // Get playlist videos
    getVideos() {
        const playlistStateManager = getPlaylistStateManager();
        if (playlistStateManager) {
            return playlistStateManager.getVideos();
        }
        return appState?.get('currentPlaylistVideos');
    }
    
    // Get current playlist ID
    getPlaylistId() {
        const playlistStateManager = getPlaylistStateManager();
        if (playlistStateManager) {
            return playlistStateManager.getPlaylistId();
        }
        return appState?.get('currentPlaylistId');
    }
}

// Create instance and export
export const playlist = new Playlist();

// Also attach to global namespace for backward compatibility during transition
if (typeof window !== 'undefined') {
    if (!window.Traktor) {
        window.Traktor = {};
    }
    if (!window.Traktor.Modules) {
        window.Traktor.Modules = {};
    }
    window.Traktor.Modules.Playlist = Playlist;
    window.Traktor.Modules.playlist = playlist;
}
