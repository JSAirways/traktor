/**
 * PIN Entry Modal JavaScript
 * Handles PIN modal opening, auto-validation on 4 digits, and error handling
 * 
 * Note: Bootstrap automatically handles:
 * - Modal opening via data-bs-toggle="modal" and data-bs-target attributes
 * - Focus management (using autofocus attribute in HTML)
 * - Backdrop creation and management
 * - Keyboard handling (ESC key)
 */

import { TimingConstants } from '../../core/constants.js';
import { LoadingStateManager } from '../../core/loading-state-manager.js';
import { setupModalAccessibility, setupModalFocus } from '../../core/modal-utils.js';
import { makeRequest, getCsrfToken } from '../../core/utils.js';
import { errorHandler } from '../../core/error-handler.js';

// Initialize loading state manager
const loadingManager = LoadingStateManager ? new LoadingStateManager({
    loadingElementId: 'pinEntryLoading',
    errorElementId: 'pinEntryError',
    loadingShowClass: 'block',
    loadingHideClass: 'none',
    errorShowClass: 'block',
    errorHideClass: 'd-none'
}) : null;

// Cache DOM elements
let pinInput = null;
let usernameInput = null;
let currentChildUsername = null;
let currentRedirectUrl = null;

/**
 * Validates PIN via AJAX request
 * Auto-triggered when 4 digits are entered
 */
async function validatePin() {
    if (!pinInput || !usernameInput) {
        return;
    }
    
    const pin = pinInput.value;
    const username = usernameInput.value;
    
    if (pin.length !== 4 || !username) {
        return;
    }
    
    // Show loading state
    pinInput.disabled = true;
    if (loadingManager) {
        loadingManager.showLoading();
        loadingManager.hideError();
    }
    
    // Get CSRF token using utility function
    const csrfToken = getCsrfToken?.() || '';
    
    const hideLoading = () => {
        pinInput.disabled = false;
        if (loadingManager) {
            loadingManager.hideLoading();
        }
    };
    
    const showError = (message) => {
        pinInput.value = '';
        pinInput.classList.add('is-invalid');
        pinInput.focus();
        
        if (loadingManager) {
            loadingManager.showError(message, 'messages.error_occurred', 'An error occurred. Please try again.');
        }
    };
    
    if (makeRequest) {
        try {
            // Prepare request body with redirect URL if available
            const requestBody = {
                username: username,
                pin: pin
            };
            
            // Add redirect URL if available
            const redirectUrl = currentRedirectUrl || window.pinEntryRedirectUrl;
            if (redirectUrl) {
                requestBody.intended_url = redirectUrl;
            }
            
            const response = await makeRequest('/api/view/validate-pin', {
                method: 'POST',
                body: requestBody,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                responseType: 'json'
            });
            
            const data = response.data || response;
            hideLoading();
            
            if (data?.success) {
                // Success - redirect to intended URL or gallery (session is now created)
                const redirectUrl = currentRedirectUrl || data.redirect_url || window.pinEntryRedirectUrl;
                if (redirectUrl) {
                    window.location.href = redirectUrl;
                } else {
                    // Fallback: reload current page (session should now be valid)
                    window.location.reload();
                }
            } else {
                showError(data?.message || null);
                if (!data?.message && loadingManager) {
                    loadingManager.showError(null, 'auth.invalid_pin', 'Invalid PIN. Please try again.');
                }
            }
        } catch (error) {
            hideLoading();
            if (errorHandler?.handleTimeoutError) {
                errorHandler.handleTimeoutError(error, {
                    showToast: false,
                    logToConsole: true,
                    context: { action: 'validatePin' }
                });
            } else if (errorHandler?.handle) {
                errorHandler.handle(error, {
                    showToast: false,
                    logToConsole: true,
                    context: { action: 'validatePin' }
                });
            }
            showError();
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const pinEntryModal = document.getElementById('pinEntryModal');
    if (!pinEntryModal) return;
    
    // Cache DOM elements
    pinInput = document.getElementById('pinEntryPin');
    usernameInput = document.getElementById('pinEntryUsername');
    
    // Setup accessibility
    if (setupModalAccessibility) {
        setupModalAccessibility('pinEntryModal');
    }
    
    // Setup autofocus for PIN entry modal (works for both data-attribute and programmatic opens)
    if (setupModalFocus && TimingConstants) {
        setupModalFocus('pinEntryModal', '#pinEntryPin', TimingConstants.MODAL_FOCUS_DELAY);
    }
    
    // Handle modal show event
    pinEntryModal.addEventListener('show.bs.modal', (event) => {
        // Get child username from trigger button OR from script tag data attribute
        const triggerButton = event.relatedTarget;
        let username = null;
        let redirectUrl = null;
        
        if (triggerButton) {
            // Opened via button click (profile selection page)
            username = triggerButton.getAttribute('data-child-username');
            const slug = triggerButton.getAttribute('data-child-slug');
            // Check if there's a stored redirect URL (from URL parameter)
            redirectUrl = window.pinEntryRedirectUrl || (slug ? `/${slug}/gallery` : null);
        } else {
            // Opened programmatically (gallery page when session missing)
            const scriptTag = document.querySelector('script[data-pin-username]');
            if (scriptTag) {
                username = scriptTag.getAttribute('data-pin-username');
            }
            redirectUrl = window.pinEntryRedirectUrl;
        }
        
        if (username) {
            currentChildUsername = username;
            const usernameInputEl = document.getElementById('pinEntryUsername');
            if (usernameInputEl) {
                usernameInputEl.value = username;
            }
        }
        
        // Store redirect URL
        if (redirectUrl) {
            currentRedirectUrl = redirectUrl;
        }
        
        // Reset form state
        if (pinInput) {
            pinInput.value = '';
            pinInput.classList.remove('is-invalid');
        }
        if (loadingManager) {
            loadingManager.reset();
        }
    });
    
    // Explicitly focus PIN input when modal is fully shown (after animation)
    pinEntryModal.addEventListener('shown.bs.modal', () => {
        if (pinInput) {
            // Use the same delay as setupModalFocus for consistency
            const delay = TimingConstants?.MODAL_FOCUS_DELAY || 150;
            setTimeout(() => {
                pinInput.focus();
            }, delay);
        }
    });
    
    // Setup PIN input handler (use cached element)
    if (pinInput && usernameInput) {
        // Only allow numeric input
        pinInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
            
            // Limit to 4 digits
            if (e.target.value.length > 4) {
                e.target.value = e.target.value.slice(0, 4);
            }
            
            // Auto-validate when 4 digits entered
            if (e.target.value.length === 4) {
                validatePin();
            }
        });
        
        // Prevent form submission on Enter (we auto-submit at 4 digits)
        pinInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (this.value.length === 4) {
                    validatePin();
                }
            }
        });
    }
    
    // Clear state when modal is hidden
    pinEntryModal.addEventListener('hidden.bs.modal', () => {
        currentChildUsername = null;
        currentRedirectUrl = null;
        
        if (pinInput) {
            pinInput.value = '';
            pinInput.classList.remove('is-invalid');
        }
        if (loadingManager) {
            loadingManager.reset();
        }
    });
    
    // Bootstrap automatically handles modal opening via data-bs-toggle="modal" and data-bs-target="#pinEntryModal"
    // No manual click handlers needed
});

/**
 * Open PIN entry modal programmatically
 * @param {string} username - Username to pre-fill
 * @param {string} redirectUrl - URL to redirect to after successful PIN entry
 */
function openPinEntryModal(username, redirectUrl = null) {
    const modal = document.getElementById('pinEntryModal');
    if (!modal) return;
    
    // Set the username in the modal
    const usernameInputEl = document.getElementById('pinEntryUsername');
    if (usernameInputEl) {
        usernameInputEl.value = username;
    }
    
    // Store redirect URL
    if (redirectUrl) {
        currentRedirectUrl = redirectUrl;
        window.pinEntryRedirectUrl = redirectUrl;
    }
    
    // Open the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

export { validatePin, openPinEntryModal };

// Also expose globally for easier access from inline scripts
if (typeof window !== 'undefined') {
    if (!window.Traktor) {
        window.Traktor = {};
    }
    window.Traktor.openPinEntryModal = openPinEntryModal;
}
