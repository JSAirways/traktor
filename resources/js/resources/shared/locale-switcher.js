/**
 * Locale Switcher
 * 
 * No JavaScript needed - forms submit naturally.
 * This file is kept for backward compatibility but does nothing.
 * The locale switching now uses standard Laravel form submission with redirect.
 */

/**
 * Initialize locale switcher (no-op for backward compatibility)
 */
function initLocaleSwitcher() {
    // Forms submit naturally - no JavaScript interception needed
    // This follows Laravel best practices: form POST → controller → redirect
}

// Export for potential external use
export { initLocaleSwitcher };

// Also attach to global namespace for backward compatibility
if (typeof window !== 'undefined') {
    if (!window.Traktor) {
        window.Traktor = {};
    }
    if (!window.Traktor.Resources) {
        window.Traktor.Resources = {};
    }
    if (!window.Traktor.Resources.Shared) {
        window.Traktor.Resources.Shared = {};
    }
    window.Traktor.Resources.Shared.localeSwitcher = {
        init: initLocaleSwitcher
    };
}
