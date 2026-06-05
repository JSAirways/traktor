/**
 * Admin Password Modal JavaScript
 * Handles admin password modal initialization and behavior
 * 
 * Note: Bootstrap automatically handles:
 * - Modal opening via data-bs-toggle="modal" and data-bs-target attributes
 * - Focus management (using autofocus attribute in HTML)
 * - Backdrop creation and management
 * - Keyboard handling (ESC key)
 * - Trigger button tracking (via event.relatedTarget)
 */

import { setupModalAccessibility, setupModalFocus, setupModalInputReset, openModal } from '../../core/modal-utils.js';
import { isFullscreen, exitFullscreen, getCsrfToken, makeRequest, getTranslation, updateCsrfToken } from '../../core/utils.js';

/**
 * Exits fullscreen and waits for it to complete
 * @returns {Promise<void>} Promise that resolves when fullscreen has exited
 */
function exitFullscreenAsync() {
    if (!isFullscreen?.()) {
        return Promise.resolve();
    }
    
    exitFullscreen();
    
    // Wait for fullscreen to exit
    return new Promise((resolve) => {
        const checkExit = () => {
            if (!isFullscreen?.()) {
                resolve();
            } else {
                setTimeout(checkExit, 50);
            }
        };
        checkExit();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const adminPasswordModal = document.getElementById('adminPasswordModal');
    if (!adminPasswordModal) return;
    
    // Setup accessibility (includes blurring focused elements inside and outside modal)
    if (setupModalAccessibility) {
        setupModalAccessibility('adminPasswordModal');
    }
    
    // Setup autofocus (Bootstrap handles this via autofocus attribute, but ensure it works)
    if (setupModalFocus) {
        setupModalFocus('adminPasswordModal', '#adminPassword', 100);
    }
    
    // Setup form reset with error preservation (for server-side validation errors)
    if (setupModalInputReset) {
        setupModalInputReset('adminPasswordModal', 'adminPassword');
    }
    
    // Get form reference
    const adminPasswordForm = document.getElementById('adminPasswordForm');
    
    /**
     * Refresh CSRF token from server and update both form and meta tag
     * This ensures we always have a fresh token, even if the page is cached
     * @returns {Promise<void>} Promise that resolves when token is refreshed
     */
    async function refreshCsrfToken() {
        if (!adminPasswordForm || !makeRequest) return;
        
        try {
            const response = await makeRequest('/csrf-token', {
                method: 'GET'
            });
            
            // Extract data from response object
            const data = response.data || response; // Backward compatibility fallback
            const newToken = data?.token || data?.data?.token;
            if (newToken) {
                // Update form input
                const csrfInput = adminPasswordForm.querySelector('input[name="_token"]');
                if (csrfInput) {
                    csrfInput.value = newToken;
                }
                
                // Update meta tag (for future use)
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                if (csrfMeta) {
                    csrfMeta.content = newToken;
                }
            }
        } catch (error) {
            // Fallback to existing token if fetch fails
            if (getCsrfToken) {
                const existingToken = getCsrfToken();
                if (existingToken) {
                    const csrfInput = adminPasswordForm.querySelector('input[name="_token"]');
                    if (csrfInput) {
                        csrfInput.value = existingToken;
                    }
                }
            }
        }
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        const passwordField = document.getElementById('adminPassword');
        if (passwordField) {
            passwordField.classList.add('is-invalid');
            passwordField.focus();
        }
        
        // Show error message using existing error div
        const errorDiv = document.getElementById('adminPasswordError');
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.classList.remove('d-none');
            errorDiv.classList.add('d-block');
        }
    }
    
    // Exit fullscreen before showing modal (modals are not visible in fullscreen)
    adminPasswordModal.addEventListener('show.bs.modal', async (event) => {
        // Close options menu offcanvas if open
        const optionsMenuOffcanvas = document.getElementById('optionsMenuOffcanvas');
        if (optionsMenuOffcanvas && typeof window !== 'undefined' && window.bootstrap?.Offcanvas) {
            const offcanvasInstance = window.bootstrap.Offcanvas.getInstance(optionsMenuOffcanvas);
            if (offcanvasInstance) {
                offcanvasInstance.hide();
            }
        }
        
        if (isFullscreen?.()) {
            // Prevent modal from opening immediately
            event.preventDefault();
            
            // Exit fullscreen first, then open modal
            await exitFullscreenAsync();
            
            // Small delay to ensure DOM is ready after fullscreen exit
            setTimeout(() => {
                // Remove any existing backdrop that might be in an inconsistent state
                const existingBackdrop = document.querySelector('.modal-backdrop');
                if (existingBackdrop) {
                    existingBackdrop.remove();
                }
                
                // Get or create modal instance
                let modalInstance = null;
                if (typeof window !== 'undefined' && window.bootstrap?.Modal) {
                    // Try to get existing instance first
                    modalInstance = window.bootstrap.Modal.getInstance(adminPasswordModal);
                    
                    // If instance exists, dispose it to ensure clean state
                    if (modalInstance) {
                        modalInstance.dispose();
                    }
                    
                    // Create new modal instance with explicit backdrop option
                    modalInstance = new window.bootstrap.Modal(adminPasswordModal, {
                        backdrop: true,
                        keyboard: true,
                        focus: true
                    });
                    
                    // Show modal (Bootstrap will create backdrop automatically)
                    modalInstance.show();
                }
            }, 150);
        }
    }, { once: false });
    
    // Handle form submission via AJAX to prevent 419 errors
    if (adminPasswordForm && makeRequest) {
        adminPasswordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const form = e.target;
            const passwordField = document.getElementById('adminPassword');
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton?.textContent || '';
            
            // Clear previous errors
            if (passwordField) {
                passwordField.classList.remove('is-invalid');
            }
            const errorDiv = document.getElementById('adminPasswordError');
            if (errorDiv) {
                errorDiv.classList.add('d-none');
                errorDiv.textContent = '';
            }
            
            // Disable submit button
            if (submitButton) {
                submitButton.disabled = true;
                const loggingInText = getTranslation?.('auth.logging_in', 'Logging in...') || 'Logging in...';
                submitButton.textContent = loggingInText;
            }
            
            // Create form data (include CSRF token like password login modal)
            // Note: Even though route is excluded, including token ensures compatibility
            // and works the same way as the password login modal
            const formData = new FormData(form);
            
            // Ensure CSRF token is in headers (makeRequest will add it automatically, but be explicit)
            const csrfToken = getCsrfToken();
            
            // Submit via AJAX
            try {
                const response = await makeRequest(form.action, {
                    method: 'POST',
                    body: formData,
                    responseType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken || '',
                        'Accept': 'application/json'
                    }
                });
                
                // Extract data from response object
                const data = response.data || response; // Backward compatibility fallback
                
                // Update CSRF token if provided in response (session was regenerated)
                if (data?.csrf_token) {
                    updateCsrfToken(data.csrf_token);
                }
                
                // Success - redirect to admin dashboard
                const redirectUrl = data?.redirect || data?.data?.redirect || '/admin';
                window.location.href = redirectUrl;
            } catch (error) {
                // Re-enable submit button
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText;
                }
                
                // Handle different error types
                let errorMessage = 'An error occurred. Please try again.';
                
                // CSRF token refresh failed (automatic retry should handle this, but handle gracefully if it fails)
                if (error?.csrfRefreshFailed) {
                    errorMessage = error.message || 'Session expired. Please refresh the page and try again.';
                }
                // Validation errors (422)
                else if (error?.status === 422) {
                    try {
                        const errorData = error.responseData || (error.response ? JSON.parse(error.response) : {});
                        if (errorData.errors?.password) {
                            errorMessage = Array.isArray(errorData.errors.password) 
                                ? errorData.errors.password[0] 
                                : errorData.errors.password;
                        } else if (errorData.message) {
                            errorMessage = errorData.message;
                        } else {
                            errorMessage = getTranslation?.('auth.invalid_password', 'Invalid password. Please try again.') || 'Invalid password. Please try again.';
                        }
                    } catch (e) {
                        errorMessage = getTranslation?.('auth.invalid_password', 'Invalid password. Please try again.') || 'Invalid password. Please try again.';
                    }
                }
                // Network errors
                else if (error?.status === 0 || error?.message?.includes('Network')) {
                    errorMessage = getTranslation?.('messages.request_timeout', 'Network error. Please check your connection and try again.') || 'Network error. Please check your connection and try again.';
                }
                // Generic errors
                else {
                    if (error?.message) {
                        errorMessage = error.message;
                    } else {
                        errorMessage = getTranslation?.('common.error_occurred', errorMessage) || errorMessage;
                    }
                }
                
                showError(errorMessage);
            }
        });
    }
    
    // Check if there are validation errors and show modal programmatically
    const adminPasswordField = document.getElementById('adminPassword');
    if (adminPasswordField?.classList.contains('is-invalid')) {
        // Exit fullscreen if needed before opening modal programmatically
        exitFullscreenAsync().then(() => {
            // Open modal programmatically with focus callback
            if (openModal) {
                openModal('adminPasswordModal', () => {
                    const passwordField = document.getElementById('adminPassword');
                    if (passwordField) {
                        setTimeout(() => {
                            passwordField.focus();
                        }, 100);
                    }
                });
            }
        });
    }
});
