/**
 * Navbar Module
 * Handles navbar visibility and styling for different view modes
 */

import { appState } from '../core/state.js';

export class Navbar {
    constructor() {
        this.navbar = document.querySelector('.top-navbar');
        this.originalParent = null;
        this.nextSibling = null;
        this.init();
    }
    
    init() {
        // No longer needed - player is on separate page
        // Navbar mode is set directly by player page
    }
    
    // Get navbar element
    getNavbar() {
        return this.navbar;
    }
    
    // Set player view mode styling
    setPlayerViewMode(enabled) {
        if (!this.navbar) return;
        
        if (enabled) {
            this.navbar.classList.add('player-view-mode');
            this.navbar.classList.remove('hidden');
            this.navbar.classList.remove('bg-dark');
            if (appState) {
                appState.set('navbarPlayerViewMode', true);
            }
        } else {
            this.navbar.classList.remove('player-view-mode');
            this.navbar.classList.remove('hidden');
            this.navbar.classList.add('bg-dark');
            if (appState) {
                appState.set('navbarPlayerViewMode', false);
            }
        }
    }
    
    // Show navbar
    show() {
        if (!this.navbar) return;
        this.navbar.classList.remove('hidden');
        if (appState) {
            appState.set('navbarHidden', false);
        }
    }
    
    // Hide navbar
    hide() {
        if (!this.navbar) return;
        this.navbar.classList.add('hidden');
        if (appState) {
            appState.set('navbarHidden', true);
        }
    }
    
    // Move navbar into fullscreen element
    moveToFullscreen(fullscreenElement) {
        if (!this.navbar || !fullscreenElement) return;
        
        // Store original position
        if (this.navbar.parentNode) {
            this.originalParent = this.navbar.parentNode;
            this.nextSibling = this.navbar.nextSibling;
            
            // Move into fullscreen element
            fullscreenElement.insertBefore(this.navbar, fullscreenElement.firstChild);
        }
    }
    
    // Restore navbar from fullscreen
    restoreFromFullscreen() {
        if (!this.navbar || !this.originalParent) return;
        
        if (this.nextSibling && this.nextSibling.parentNode === this.originalParent) {
            this.originalParent.insertBefore(this.navbar, this.nextSibling);
        } else {
            this.originalParent.appendChild(this.navbar);
        }
        
        this.originalParent = null;
        this.nextSibling = null;
    }
}

// Create instance and export
export const navbar = new Navbar();

// Also attach to global namespace for backward compatibility during transition
if (typeof window !== 'undefined') {
    if (!window.Traktor) {
        window.Traktor = {};
    }
    if (!window.Traktor.Modules) {
        window.Traktor.Modules = {};
    }
    window.Traktor.Modules.Navbar = Navbar;
    window.Traktor.Modules.navbar = navbar;
}
