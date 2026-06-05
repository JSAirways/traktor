/**
 * Global Namespace System
 * Provides backward compatibility namespace for modules that still use global access
 * Most modules now use ES6 modules, but this maintains compatibility during transition
 */

(function(global) {
    'use strict';
    
    // Initialize global namespace if it doesn't exist
    global.Traktor = global.Traktor || {};
    global.Traktor.Core = global.Traktor.Core || {};
    global.Traktor.Modules = global.Traktor.Modules || {};
    global.Traktor.Resources = global.Traktor.Resources || {};
    global.Traktor.Admin = global.Traktor.Admin || {};
    
    // Export namespace for use in other files
    // Files will add their exports to these namespaces for backward compatibility
    // Example: Traktor.Core.utils = { formatTime: function() {...} };
    
})(typeof window !== 'undefined' ? window : typeof global !== 'undefined' ? global : this);
