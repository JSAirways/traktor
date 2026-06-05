{{--
    Video Container Component
    
    Video container with iframe, pause overlay, and custom controls.
    Reusable player iframe structure.
    
    @prop array $catGifs - Array of cat GIF filenames for pause overlay
--}}
@props([
    'catGifs' => [],
])

<!-- Layer 1: YouTube iframe (bottom layer) -->
<div id="videoContainer" class="video-container d-flex align-items-center justify-content-center" data-layer="video">
    <div id="player" class="player-iframe"></div>
</div>

<!-- Layer 2: Click Blocker (intercepts all clicks) -->
<div class="click-blocker-layer" data-layer="click-blocker"></div>

<!-- Layer 3: Overlay (blur + cat GIF when paused) -->
<div class="overlay-layer" data-layer="overlay">
    <div class="video-overlay-effect"></div>
    <div class="cat-gif-container d-none">
        <img id="catGif" src="" alt="Paused" />
    </div>
</div>




