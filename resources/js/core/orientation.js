/**
 * Orientation Detection Utility
 * 
 * Detects device orientation and adds/removes body classes for CSS targeting.
 * This simplifies CSS by using body.is-landscape instead of media queries.
 */

let orientationInitialized = false;

/**
 * Initialize orientation detection
 * Adds 'is-landscape' or 'is-portrait' class to body based on orientation
 */
export function initOrientationDetection() {
    if (orientationInitialized) return;
    
    /**
     * Update orientation classes on body
     */
    const updateOrientation = () => {
        const isLandscape = window.innerWidth > window.innerHeight;
        const isMobile = window.innerWidth < 768; // Bootstrap md breakpoint
        
        if (isMobile && isLandscape) {
            document.body.classList.add('is-landscape');
            document.body.classList.remove('is-portrait');
        } else {
            document.body.classList.remove('is-landscape');
            if (isMobile) {
                document.body.classList.add('is-portrait');
            } else {
                // Desktop - remove both classes
                document.body.classList.remove('is-portrait');
            }
        }
    };
    
    // Update immediately
    updateOrientation();
    
    // Update on orientation change
    window.addEventListener('orientationchange', () => {
        // Small delay to ensure accurate measurements after orientation change
        setTimeout(updateOrientation, 100);
    });
    
    // Also listen for resize (some devices fire resize instead of orientationchange)
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(updateOrientation, 150);
    });
    
    orientationInitialized = true;
}


