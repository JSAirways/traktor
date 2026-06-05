/**
 * Bootstrap / Application Initialization
 * 
 * This file ensures Bootstrap JS is loaded and available globally
 * PWA and asset version checker are loaded as ES6 modules
 */

// Import Bootstrap JavaScript and make it available globally
// Bootstrap 5 exports as a default export
import bootstrap from 'bootstrap/dist/js/bootstrap.bundle.min.js';

// Make Bootstrap available globally for data-attribute functionality
// This allows data-bs-toggle, data-bs-target, etc. to work
if (typeof window !== 'undefined') {
    window.bootstrap = bootstrap;
}

// Initialize PWA and asset version checker when DOM is ready
// These will be loaded as separate modules
function initializeApp() {
    // PWA initialization (from Traktor.Core.pwaInstaller)
    if (typeof window !== 'undefined' && window.Traktor?.Core?.pwaInstaller?.initPWA) {
        window.Traktor.Core.pwaInstaller.initPWA();
    }
    
    // Asset version checker (from Traktor.Core.assetVersionChecker)
    if (typeof window !== 'undefined' && window.Traktor?.Core?.assetVersionChecker?.checkAssetVersion) {
        window.Traktor.Core.assetVersionChecker.checkAssetVersion();
    }
}

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initializeApp();
    });
} else {
    initializeApp();
}
