/**
 * Bootstrap / Application Initialization
 * 
 * iOS 10.3.4 / PS4 compatibility: No ES6 modules
 * Polyfills, axios, PWA, and asset version checker are loaded separately
 * This file just ensures initialization happens in the correct order
 */

(function(global) {
    'use strict';
    
    // Note: Polyfills (url-search-params-polyfill.js, text-encoder-polyfill.js) 
    // should be loaded before this file via Vite entry points
    
    // Note: Axios is loaded from node_modules and will be bundled by Vite
    // It should be available globally as window.axios
    
    // Initialize PWA and asset version checker when DOM is ready
    // These will be loaded as separate modules and available in global namespace
    function initializeApp() {
        // PWA initialization (from Traktor.Core.pwaInstaller)
        if (global.Traktor && global.Traktor.Core && global.Traktor.Core.pwaInstaller && global.Traktor.Core.pwaInstaller.initPWA) {
            global.Traktor.Core.pwaInstaller.initPWA();
        }
        
        // Asset version checker (from Traktor.Core.assetVersionChecker)
        if (global.Traktor && global.Traktor.Core && global.Traktor.Core.assetVersionChecker && global.Traktor.Core.assetVersionChecker.checkAssetVersion) {
            global.Traktor.Core.assetVersionChecker.checkAssetVersion();
        }
    }
    
    // Initialize on page load
    // iOS 10 compatibility: Use regular functions instead of arrow functions
if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initializeApp();
  });
} else {
        initializeApp();
}
    
})(typeof window !== 'undefined' ? window : typeof global !== 'undefined' ? global : this);
