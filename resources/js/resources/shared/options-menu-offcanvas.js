/**
 * Options Menu Offcanvas JavaScript
 * Handles options menu offcanvas initialization and behavior
 * 
 * Exits fullscreen mode when the offcanvas menu opens to ensure proper visibility.
 * Manually handles language dropdown toggle to avoid Popper.js positioning conflicts
 * inside offcanvas elements. Bootstrap's Dropdown with Popper.js has known issues
 * when used inside offcanvas, so manual class/attribute toggling is more reliable.
 */

import { isFullscreen, exitFullscreen } from '../../core/utils.js';

/**
 * Initialize language dropdown with manual toggle handling
 * 
 * Bootstrap's Dropdown with Popper.js has known positioning issues inside offcanvas elements.
 * Manual handling ensures reliable behavior by directly toggling CSS classes and aria attributes.
 * This approach is simpler and more reliable than trying to configure Popper.js static positioning.
 */
function initLanguageDropdown() {
    const optionsMenuOffcanvas = document.getElementById('optionsMenuOffcanvas');
    const localeSwitcherBtn = document.getElementById('optionsLocaleSwitcherBtn');
    
    if (!optionsMenuOffcanvas || !localeSwitcherBtn) {
        return;
    }

    // Find the dropdown menu - it's now inside a div.dropdown that's a sibling to the button
    let dropdownContainer = localeSwitcherBtn.closest?.('.dropdown');
    if (!dropdownContainer && localeSwitcherBtn.parentElement) {
        // Fallback for older browsers that don't support closest
        let parent = localeSwitcherBtn.parentElement;
        while (parent && parent !== document.body) {
            if (parent.classList?.contains('dropdown')) {
                dropdownContainer = parent;
                break;
            }
            parent = parent.parentElement;
        }
    }
    const dropdownMenu = dropdownContainer?.querySelector('.dropdown-menu');
    
    // Ensure dropdown menu exists
    if (!dropdownMenu || !dropdownMenu.classList.contains('dropdown-menu')) {
        return;
    }

    // Verify the button is actually inside the options menu offcanvas
    if (!optionsMenuOffcanvas.contains(localeSwitcherBtn)) {
        return;
    }

    // Check if already initialized to prevent duplicate event listeners
    if (localeSwitcherBtn.hasAttribute('data-dropdown-initialized')) {
        return;
    }

    // Mark as initialized
    localeSwitcherBtn.setAttribute('data-dropdown-initialized', 'true');

    // Remove data-bs-toggle to prevent Bootstrap from auto-initializing
    // We'll handle it manually to avoid Popper.js issues in offcanvas
    localeSwitcherBtn.removeAttribute('data-bs-toggle');

    // Manual click handler to toggle dropdown
    localeSwitcherBtn.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        
        const isExpanded = localeSwitcherBtn.getAttribute('aria-expanded') === 'true';
        
        if (isExpanded) {
            // Close dropdown
            localeSwitcherBtn.setAttribute('aria-expanded', 'false');
            dropdownMenu.classList.remove('show');
        } else {
            // Open dropdown
            localeSwitcherBtn.setAttribute('aria-expanded', 'true');
            dropdownMenu.classList.add('show');
        }
    });

    // Close dropdown when clicking outside (on offcanvas body)
    const handleOffcanvasClick = (event) => {
        const target = event.target;
        const isClickOnButton = localeSwitcherBtn.contains(target);
        const isClickOnMenu = dropdownMenu.contains(target);
        let isClickOnDropdownItem = target.closest?.('.dropdown-item') !== null;
        if (!isClickOnDropdownItem && target.parentElement) {
            // Fallback for older browsers
            let parent = target.parentElement;
            while (parent && parent !== document.body) {
                if (parent.classList?.contains('dropdown-item')) {
                    isClickOnDropdownItem = true;
                    break;
                }
                parent = parent.parentElement;
            }
        }
        let isClickOnLink = target.closest?.('a.nav-link') !== null;
        if (!isClickOnLink && target.parentElement) {
            // Fallback for older browsers
            let parent = target.parentElement;
            while (parent && parent !== document.body) {
                if (parent.tagName === 'A' && parent.classList?.contains('nav-link')) {
                    isClickOnLink = true;
                    break;
                }
                parent = parent.parentElement;
            }
        }
        
        // Don't interfere with link clicks - let them navigate normally
        if (isClickOnLink) {
            // Close offcanvas when link is clicked
            // Check for Bootstrap availability
            if (typeof window !== 'undefined' && window.bootstrap?.Offcanvas) {
                const offcanvasInstance = window.bootstrap.Offcanvas.getInstance(optionsMenuOffcanvas);
                if (offcanvasInstance) {
                    offcanvasInstance.hide();
                }
            }
            return;
        }
        
        if (!isClickOnButton && !isClickOnMenu && !isClickOnDropdownItem) {
            localeSwitcherBtn.setAttribute('aria-expanded', 'false');
            dropdownMenu.classList.remove('show');
        }
    };
    
    optionsMenuOffcanvas.addEventListener('click', handleOffcanvasClick);

    // Close dropdown when offcanvas is hidden
    optionsMenuOffcanvas.addEventListener('hidden.bs.offcanvas', () => {
        localeSwitcherBtn.setAttribute('aria-expanded', 'false');
        dropdownMenu.classList.remove('show');
    });
}

/**
 * Cleanup duplicate backdrops
 */
function cleanupBackdrops() {
    const backdrops = document.querySelectorAll('.offcanvas-backdrop');
    if (backdrops.length > 1) {
        // Keep only the last one (most recent)
        for (let i = 0; i < backdrops.length - 1; i++) {
            backdrops[i].remove();
        }
    }
}

/**
 * Initialize options menu offcanvas
 * Exits fullscreen when offcanvas opens
 * Initializes language dropdown when offcanvas is shown
 * Prevents duplicate backdrop issues
 */
function init() {
    // Wait for Bootstrap to be available
    if (typeof window === 'undefined' || !window.bootstrap?.Offcanvas) {
        // Bootstrap not ready yet, wait a bit and retry
        setTimeout(() => {
            if (typeof window !== 'undefined' && window.bootstrap?.Offcanvas) {
                init();
            }
        }, 100);
        return;
    }
    
    const optionsMenuOffcanvas = document.getElementById('optionsMenuOffcanvas');
    if (!optionsMenuOffcanvas) return;

    // Check if offcanvas is already shown (e.g., on page load)
    // Bootstrap adds 'show' class when offcanvas is visible
    if (optionsMenuOffcanvas.classList.contains('show')) {
        cleanupBackdrops();
        // Add class to body when offcanvas is open (for styling/animations)
        document.body.classList.add('options-menu-open');
        // Initialize dropdown immediately if offcanvas is already shown
        setTimeout(() => {
            initLanguageDropdown();
        }, 100);
    }

    optionsMenuOffcanvas.addEventListener('shown.bs.offcanvas', (event) => {
        cleanupBackdrops();
        
        // Initialize dropdown after offcanvas is fully shown
        // Use a small timeout to ensure DOM is fully ready
        setTimeout(() => {
            // Double-check that this is still the options menu offcanvas being shown
            const currentOffcanvas = event.target;
            if (currentOffcanvas?.id === 'optionsMenuOffcanvas') {
                initLanguageDropdown();
            }
        }, 100);
    });

    optionsMenuOffcanvas.addEventListener('show.bs.offcanvas', () => {
        // Exit fullscreen if active
        if (isFullscreen?.()) {
            if (exitFullscreen) {
                exitFullscreen();
            }
        }
        
        // Cleanup any existing backdrops before showing (prevent duplicates)
        cleanupBackdrops();
        
        // Add class to body when offcanvas opens (for styling/animations)
        // This happens immediately, before the offcanvas slides in
        document.body.classList.add('options-menu-open');
    });

    optionsMenuOffcanvas.addEventListener('hidden.bs.offcanvas', () => {
        // Remove class when options menu offcanvas is closed
        document.body.classList.remove('options-menu-open');
    });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

export { init, initLanguageDropdown, cleanupBackdrops };
