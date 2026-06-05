/**
 * Admin Layout JavaScript
 * Handles sidebar offcanvas, toast initialization, and profile selection
 */

document.addEventListener('DOMContentLoaded', () => {
    // Sidebar offcanvas handling
    const offcanvasElement = document.getElementById('sidebarMenu');
    if (offcanvasElement) {
        // Close offcanvas when nav links are clicked
        const navLinks = offcanvasElement.querySelectorAll('.nav-link');
        for (const link of navLinks) {
            link.addEventListener('click', () => {
                // Get existing instance (created by Bootstrap via data-bs-toggle)
                if (typeof window !== 'undefined' && window.bootstrap?.Offcanvas) {
                    const offcanvas = window.bootstrap.Offcanvas.getInstance(offcanvasElement);
                    if (offcanvas) {
                        offcanvas.hide();
                    }
                }
            });
        }
        
        // Cleanup duplicate backdrops on sidebar offcanvas events
        offcanvasElement.addEventListener('shown.bs.offcanvas', () => {
            // Remove any duplicate backdrops
            const backdrops = document.querySelectorAll('.offcanvas-backdrop');
            if (backdrops.length > 1) {
                // Keep only the last one (most recent)
                for (let i = 0; i < backdrops.length - 1; i++) {
                    backdrops[i].remove();
                }
            }
        });
    }
    
    // Toast initialization
    const toastContainer = document.getElementById('toastContainer');
    if (toastContainer && typeof window !== 'undefined' && window.bootstrap?.Toast) {
        // Find all toast elements on the page
        const toastElements = document.querySelectorAll('.toast');
        
        for (const toastEl of toastElements) {
            // Move toast to container if not already there
            if (toastEl.parentElement !== toastContainer) {
                toastContainer.appendChild(toastEl);
            }
            
            // Check if toast should auto-hide (respect data-bs-autohide attribute)
            const autohide = toastEl.getAttribute('data-bs-autohide') !== 'false';
            const delay = autohide ? 5000 : 0;
            
            // Initialize and show the toast
            const toast = new window.bootstrap.Toast(toastEl, {
                autohide: autohide,
                delay: delay
            });
            toast.show();
            
            // Remove toast element after it's hidden (only for auto-hiding toasts)
            if (autohide) {
                const handler = () => {
                    toastEl.remove();
                    toastEl.removeEventListener('hidden.bs.toast', handler);
                };
                toastEl.addEventListener('hidden.bs.toast', handler);
            }
        }
    }
    
    // Handle profile selection button click - logout and redirect to profile selection page
    const adminProfileSelectionBtn = document.getElementById('adminProfileSelectionBtn');
    if (adminProfileSelectionBtn && !adminProfileSelectionBtn.hasAttribute('data-handler-attached')) {
        adminProfileSelectionBtn.setAttribute('data-handler-attached', 'true');
        adminProfileSelectionBtn.addEventListener('click', () => {
            // Get routes from data attributes or meta tags
            let logoutRoute = adminProfileSelectionBtn.getAttribute('data-logout-route');
            if (!logoutRoute) {
                const logoutMeta = document.querySelector('meta[name="logout-route"]');
                logoutRoute = logoutMeta?.content || null;
            }
            let profileSelectionRoute = adminProfileSelectionBtn.getAttribute('data-profile-selection-route');
            if (!profileSelectionRoute) {
                const profileMeta = document.querySelector('meta[name="profile-selection-route"]');
                profileSelectionRoute = profileMeta?.content || null;
            }
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta?.content || null;
            
            if (!logoutRoute || !csrfToken) {
                return;
            }
            
            // Create and submit logout form, then redirect to profile selection
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = logoutRoute;
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
            
            // Add redirect input to go to profile selection after logout
            if (profileSelectionRoute) {
                const redirectInput = document.createElement('input');
                redirectInput.type = 'hidden';
                redirectInput.name = 'redirect';
                redirectInput.value = profileSelectionRoute;
                form.appendChild(redirectInput);
            }
            
            document.body.appendChild(form);
            
            // Submit form - will redirect to profile selection after logout
            form.submit();
        });
    }
});
