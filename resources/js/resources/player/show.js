/**
 * Player Page — orchestration only
 * Playback chrome/input lives in modules/controls.js
 */

import '../../modules/video-player.js';
import '../../modules/playlist.js';
import '../../modules/navbar.js';
import '../../modules/fullscreen.js';
import '../../modules/controls.js';

import { appState } from '../../core/state.js';
import { eventEmitter } from '../../core/events.js';
import { TimingConstants } from '../../core/constants.js';
import { getScriptData, getScriptDataJson, parseIntSafe, fixMobileViewport, setPlayerViewOverflow, hideLoadingSpinner, buildQueryString, toggleVisibility } from '../../core/utils.js';
import { analyticsTracker } from '../../core/analytics-tracker.js';

function getVideoPlayer() {
    return window.Traktor?.Modules?.videoPlayer ?? null;
}

function getPlaylist() {
    return window.Traktor?.Modules?.playlist ?? null;
}

function getNavbar() {
    return window.Traktor?.Modules?.navbar ?? null;
}

function getFullscreen() {
    return window.Traktor?.Modules?.fullscreen ?? null;
}

function getControls() {
    return window.Traktor?.Modules?.controls ?? null;
}

// DOM elements
let customControlBar = document.getElementById('customControlBar');
let returnToGalleryBtn = document.getElementById('returnToGalleryBtn');
let prevVideoBtn = document.getElementById('prevVideoBtn');
let nextVideoBtn = document.getElementById('nextVideoBtn');

// Data from script tag
let slug = getScriptData?.('data-slug') || null;
let initialVideoId = getScriptData?.('data-video-id') || null;
let initialVideoDbId = parseIntSafe?.(getScriptData?.('data-video-db-id'), null) || null;
let channelId = getScriptData?.('data-channel-id') || null;
let playlistId = getScriptData?.('data-playlist-id') || null;
let playlistVideos = getScriptDataJson?.('data-playlist-videos', []) || [];
let currentIndex = parseIntSafe?.(getScriptData?.('data-current-index'), 0) || 0;
let catGifs = getScriptDataJson?.('data-cat-gifs', []) || [];

if (typeof window !== 'undefined') {
    window.availableCatGifs = catGifs;
}

/**
 * Initialize player page
 */
function init() {
    const navbar = getNavbar();
    if (navbar?.setPlayerViewMode) {
        navbar.setPlayerViewMode(true);
    }

    if (returnToGalleryBtn && toggleVisibility) {
        toggleVisibility('returnToGalleryBtn', true);
    }

    if (playlistId && playlistVideos.length > 0 && toggleVisibility) {
        toggleVisibility('playlistNavButtons', true);
    }

    if (setPlayerViewOverflow) {
        setPlayerViewOverflow(true);
    }

    if (fixMobileViewport) {
        fixMobileViewport();
        if (TimingConstants?.MOBILE_VIEWPORT_FIX_DELAY) {
            setTimeout(() => {
                fixMobileViewport();
            }, TimingConstants.MOBILE_VIEWPORT_FIX_DELAY);
        }
    }

    const playlist = getPlaylist();
    if (playlistId && playlistVideos.length > 0 && appState?.setState) {
        appState.setState({
            currentPlaylistId: parseInt(playlistId, 10),
            currentPlaylistVideos: playlistVideos,
            currentVideoIndex: currentIndex
        });

        if (playlist?.setPlaylist) {
            playlist.setPlaylist(parseInt(playlistId, 10), playlistVideos, currentIndex);
        }
        if (playlist?.updateNavbar) {
            playlist.updateNavbar();
        }
    } else if (appState?.setState) {
        appState.setState({
            currentPlaylistId: null,
            currentPlaylistVideos: [],
            currentVideoIndex: -1
        });
    }

    setupEventListeners();

    customControlBar = document.getElementById('customControlBar');
    if (customControlBar) {
        customControlBar.classList.remove('hidden');
    }
    const controls = getControls();
    controls?.reveal?.();
    controls?.clearAutoHide?.();

    sizeVideoToFit();

    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(sizeVideoToFit, 100);
    });

    window.addEventListener('orientationchange', () => {
        setTimeout(sizeVideoToFit, 200);
    });

    let hasTriedAutoplay = false;
    const tryAutoplayOnInteraction = () => {
        const videoPlayer = getVideoPlayer();
        if (!hasTriedAutoplay && videoPlayer?.isReady?.() && videoPlayer.play) {
            hasTriedAutoplay = true;
            try {
                videoPlayer.play();
            } catch (e) {
                // Autoplay still blocked
            }
        }
    };

    document.addEventListener('touchstart', tryAutoplayOnInteraction, { once: true, passive: true });
    document.addEventListener('click', tryAutoplayOnInteraction, { once: true, passive: true });

    const videoIdToPlay = resolveVideoIdToPlay();

    if (eventEmitter?.on) {
        eventEmitter.on('player:ready', () => {
            if (customControlBar) {
                customControlBar.classList.remove('hidden');
            }
            playVideo(videoIdToPlay);
            if (hideLoadingSpinner) {
                hideLoadingSpinner('loadingSpinner');
            }
        });
    }

    const videoPlayer = getVideoPlayer();
    if (videoPlayer?.isReady?.()) {
        if (customControlBar) {
            customControlBar.classList.remove('hidden');
        }
        playVideo(videoIdToPlay);
        if (hideLoadingSpinner) {
            hideLoadingSpinner('loadingSpinner');
        }
    } else {
        setTimeout(() => {
            if (hideLoadingSpinner) {
                hideLoadingSpinner('loadingSpinner');
            }
        }, 10000);
    }

    setupProfileSelectionButton();

    if (slug && analyticsTracker) {
        analyticsTracker.init(slug);
        window.addEventListener('beforeunload', () => {
            analyticsTracker.trackAbandonedIfNeeded();
        });
    }
}

function resolveVideoIdToPlay() {
    if (playlistId && playlistVideos?.length > 0 && currentIndex >= 0 && currentIndex < playlistVideos.length) {
        const playlistVideo = playlistVideos[currentIndex];
        if (playlistVideo?.video_id) {
            return playlistVideo.video_id;
        }
    }
    return initialVideoId;
}

/**
 * Page-level events: analytics + leave-player navigation
 */
function setupEventListeners() {
    if (!eventEmitter?.on) {
        console.error('[Player] Event emitter not available - cannot set up event listeners');
        return;
    }

    eventEmitter.on('video:statechange', (data) => {
        if (!analyticsTracker || !slug) return;

        const videoPlayer = getVideoPlayer();
        const player = videoPlayer?.getPlayer?.();
        const currentVideoId = appState?.get?.('currentVideoId');
        const currentPlaylistId = appState?.get?.('currentPlaylistId');

        let videoDbId = null;
        if (playlistId && playlistVideos?.length > 0 && currentIndex >= 0 && currentIndex < playlistVideos.length) {
            videoDbId = playlistVideos[currentIndex]?.id || null;
        } else {
            videoDbId = initialVideoDbId;
        }

        if (!player || !currentVideoId || !videoDbId) return;

        const YT = window.YT;
        if (!YT) return;

        const state = data.state;
        const currentTime = player.getCurrentTime?.() || 0;
        const duration = player.getDuration?.() || 0;

        if (state === YT.PlayerState.PLAYING) {
            analyticsTracker.trackVideoResumed(videoDbId, Math.floor(currentTime), Math.floor(duration), currentPlaylistId ? parseInt(currentPlaylistId, 10) : null);
            analyticsTracker.startPositionTracking(
                videoDbId,
                () => player.getCurrentTime?.() || 0,
                () => player.getDuration?.() || 0,
                currentPlaylistId ? parseInt(currentPlaylistId, 10) : null
            );
        } else if (state === YT.PlayerState.PAUSED || state === YT.PlayerState.CUED) {
            analyticsTracker.trackVideoPaused(videoDbId, Math.floor(currentTime), Math.floor(duration), currentPlaylistId ? parseInt(currentPlaylistId, 10) : null);
            analyticsTracker.stopPositionTracking();
        } else if (state === YT.PlayerState.ENDED) {
            analyticsTracker.trackVideoCompleted(videoDbId, Math.floor(duration), currentPlaylistId ? parseInt(currentPlaylistId, 10) : null);
            analyticsTracker.stopPositionTracking();
        }
    });

    // playlist.js is the sole video:ended consumer; navigate on leave-player events only
    eventEmitter.on('video:ended-single', () => {
        navigateToGallery();
    });

    eventEmitter.on('playlist:ended', () => {
        const fullscreen = getFullscreen();
        if (fullscreen?.exitBeforeGallerySwitch) {
            fullscreen.exitBeforeGallerySwitch().then(() => {
                navigateToGallery();
            }).catch(() => {
                navigateToGallery();
            });
        } else {
            navigateToGallery();
        }
    });

    eventEmitter.on('playlist:update-url', (data) => {
        if (data.index !== undefined) {
            updatePlaylistUrl(data.index);
        }
    });
}

/**
 * Size video to fit viewport
 */
function sizeVideoToFit() {
    const player = document.getElementById('player');
    if (!player) return;

    const vw = window.innerWidth || document.documentElement.clientWidth;
    const vh = window.innerHeight || document.documentElement.clientHeight;

    const widthBased = { w: vw, h: vw * 9 / 16 };
    const heightBased = { w: vh * 16 / 9, h: vh };

    const useWidthBased = widthBased.h <= vh;
    const dims = useWidthBased ? widthBased : heightBased;

    dims.w = Math.min(dims.w, vw);
    dims.h = Math.min(dims.h, vh);

    player.style.width = `${dims.w}px`;
    player.style.height = `${dims.h}px`;
    player.style.paddingBottom = '0';
    player.style.maxWidth = `${vw}px`;
    player.style.maxHeight = `${vh}px`;
}

/**
 * Play video
 */
function playVideo(videoId) {
    const videoPlayer = getVideoPlayer();
    if (!videoId || !videoPlayer?.loadVideo) {
        if (!videoId && playlistId && playlistVideos?.length > 0 && currentIndex >= 0 && currentIndex < playlistVideos.length) {
            const playlistVideo = playlistVideos[currentIndex];
            if (playlistVideo?.video_id) {
                videoId = playlistVideo.video_id;
            }
        }

        if (!videoId) {
            console.error('[Player] Cannot play video - no video ID available', {
                initialVideoId,
                playlistId,
                playlistVideos,
                currentIndex
            });
            return;
        }
    }

    customControlBar = document.getElementById('customControlBar');
    if (customControlBar) {
        customControlBar.classList.remove('hidden');
    }

    videoPlayer.loadVideo(videoId, true);

    if (analyticsTracker && slug) {
        const currentPlaylistId = appState?.get?.('currentPlaylistId');
        let videoDbId = null;
        if (playlistId && playlistVideos?.length > 0 && currentIndex >= 0 && currentIndex < playlistVideos.length) {
            videoDbId = playlistVideos[currentIndex]?.id || null;
        } else {
            videoDbId = initialVideoDbId;
        }

        setTimeout(() => {
            const player = videoPlayer?.getPlayer?.();
            const duration = player?.getDuration?.() || null;
            if (videoDbId) {
                analyticsTracker.trackVideoStarted(
                    videoDbId,
                    currentPlaylistId ? parseInt(currentPlaylistId, 10) : null,
                    duration ? Math.floor(duration) : null
                );
            }
        }, 1000);
    }

    setTimeout(() => {
        if (videoPlayer?.isReady?.() && videoPlayer.play) {
            try {
                videoPlayer.play();
            } catch (e) {
                // Autoplay blocked
            }
        }
    }, 600);
}

/**
 * Navigate to gallery
 */
async function navigateToGallery() {
    if (!slug) {
        console.error('[Player] Cannot navigate - slug is missing');
        return;
    }

    if (analyticsTracker) {
        analyticsTracker.cleanup();
    }

    const queryParams = {};
    if (channelId && channelId !== 'all') {
        queryParams.channel = channelId;
    }

    const queryString = buildQueryString?.(queryParams) || '';
    const url = `/${slug}/gallery${queryString ? `?${queryString}` : ''}`;
    window.location.href = url;
}

/**
 * Update playlist URL via History API
 */
function updatePlaylistUrl(index) {
    const queryParams = {
        channel: channelId || 'all',
        index: index
    };

    const queryString = buildQueryString?.(queryParams) || '';
    const url = `/${slug}/player/playlist/${playlistId}${queryString ? `?${queryString}` : ''}`;
    window.history.replaceState({}, '', url);
}

/**
 * Setup profile selection button click handler
 */
function setupProfileSelectionButton() {
    const profileSelectionBtn = document.getElementById('profileSelectionBtn');
    if (profileSelectionBtn && !profileSelectionBtn.hasAttribute('data-handler-attached')) {
        profileSelectionBtn.setAttribute('data-handler-attached', 'true');
        profileSelectionBtn.addEventListener('click', () => {
            window.location.href = '/';
        });
    }
}

/**
 * Setup playlist navigation buttons
 */
function setupPlaylistButtons() {
    const playlist = getPlaylist();
    const controls = getControls();

    if (prevVideoBtn && playlist?.prev && !prevVideoBtn.hasAttribute('data-handler-attached')) {
        prevVideoBtn.setAttribute('data-handler-attached', 'true');
        prevVideoBtn.addEventListener('click', () => {
            playlist.prev();
            controls?.reveal?.();
            controls?.bumpAutoHide?.();
        });
    }

    if (nextVideoBtn && playlist?.next && !nextVideoBtn.hasAttribute('data-handler-attached')) {
        nextVideoBtn.setAttribute('data-handler-attached', 'true');
        nextVideoBtn.addEventListener('click', () => {
            playlist.next();
            controls?.reveal?.();
            controls?.bumpAutoHide?.();
        });
    }

    const fullscreen = getFullscreen();
    if (returnToGalleryBtn && !returnToGalleryBtn.hasAttribute('data-handler-attached')) {
        returnToGalleryBtn.setAttribute('data-handler-attached', 'true');

        const handleGalleryNavigation = (e) => {
            if (e) {
                e.stopPropagation();
                e.preventDefault();
            }

            if (fullscreen?.exitBeforeGallerySwitch) {
                fullscreen.exitBeforeGallerySwitch().then(() => {
                    navigateToGallery();
                }).catch(() => {
                    navigateToGallery();
                });
            } else {
                navigateToGallery();
            }
        };

        returnToGalleryBtn.addEventListener('click', handleGalleryNavigation);
        returnToGalleryBtn.addEventListener('touchend', handleGalleryNavigation, { passive: false });
    }
}

function refreshDomAndData() {
    customControlBar = document.getElementById('customControlBar');
    returnToGalleryBtn = document.getElementById('returnToGalleryBtn');
    prevVideoBtn = document.getElementById('prevVideoBtn');
    nextVideoBtn = document.getElementById('nextVideoBtn');

    slug = getScriptData?.('data-slug') || null;
    initialVideoId = getScriptData?.('data-video-id') || null;
    initialVideoDbId = parseIntSafe?.(getScriptData?.('data-video-db-id'), null) || null;
    channelId = getScriptData?.('data-channel-id') || null;
    playlistId = getScriptData?.('data-playlist-id') || null;
    playlistVideos = getScriptDataJson?.('data-playlist-videos', []) || [];
    currentIndex = parseIntSafe?.(getScriptData?.('data-current-index'), 0) || 0;
    catGifs = getScriptDataJson?.('data-cat-gifs', []) || [];

    if (typeof window !== 'undefined') {
        window.availableCatGifs = catGifs;
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        init();
        setupPlaylistButtons();
    });
} else {
    init();
    setupPlaylistButtons();
}

if (eventEmitter?.on) {
    eventEmitter.on('view:player-loaded', () => {
        refreshDomAndData();

        const videoIdToPlay = resolveVideoIdToPlay();
        if (videoIdToPlay) {
            const videoPlayer = getVideoPlayer();
            if (videoPlayer?.isReady?.()) {
                playVideo(videoIdToPlay);
            } else if (eventEmitter?.once) {
                eventEmitter.once('player:ready', () => {
                    playVideo(videoIdToPlay);
                });
            }
        }

        init();
        setupPlaylistButtons();
    });
}
