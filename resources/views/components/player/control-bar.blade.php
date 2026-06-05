{{--
    Control Bar Component
    
    Control bar with play/pause, progress, time display, and fullscreen buttons.
    All control bar UI elements.
--}}

<!-- Control Bar - Outside video container to avoid overflow clipping -->
<div id="customControlBar" class="custom-control-bar">
    <!-- Play/Pause Button -->
    <button type="button" id="customPlayPause" class="custom-control-btn custom-play-pause" aria-label="Play/Pause">
        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" viewBox="0 0 16 16" class="play-icon">
            <path d="M6 4v8a.5.5 0 0 0 .811.391l7-4.5a.5.5 0 0 0 0-.782l-7-4.5A.5.5 0 0 0 6 4zm1.5 4.678L11.956 8 7.5 3.322v5.356z"/>
        </svg>
        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" viewBox="0 0 16 16" class="pause-icon d-none">
            <path d="M5.5 3.5A.5.5 0 0 1 6 4v8a.5.5 0 0 1-1 0V4a.5.5 0 0 1 .5-.5zm5 0A.5.5 0 0 1 11 4v8a.5.5 0 0 1-1 0V4a.5.5 0 0 1 .5-.5z"/>
        </svg>
    </button>
    
    <!-- Progress Container -->
    <div class="custom-progress-container">
        <div id="customProgressBar" class="custom-progress-bar">
            <div id="customProgressFill" class="custom-progress-fill"></div>
        </div>
    </div>
    
    <!-- Time Display -->
    <div id="customTimeDisplay" class="custom-time-display">
        <span id="currentTime">0:00</span> / <span id="duration">0:00</span>
    </div>
    
    <!-- Fullscreen Button -->
    <button type="button" id="customFullscreen" class="custom-control-btn custom-fullscreen" aria-label="Fullscreen">
        <i class="bi bi-arrows-fullscreen fs-4"></i>
    </button>
</div>




