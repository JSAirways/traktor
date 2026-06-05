/**
 * Forgot Password Page JavaScript
 * Handles the back button functionality to restore password modal state
 * Handles AJAX form submission with immediate toast feedback
 */

import { SessionConstants } from '../../core/constants.js';
import { makeRequest, showToast, getTranslation } from '../../core/utils.js';

document.addEventListener('DOMContentLoaded', () => {
    const backButton = document.querySelector('[data-restore-password-modal]');
    if (backButton && SessionConstants) {
        backButton.addEventListener('click', (event) => {
            event.preventDefault();
            
            // Get stored state from sessionStorage
            const storedUsername = sessionStorage.getItem(SessionConstants.PASSWORD_FORM_USERNAME);
            
            if (storedUsername) {
                // Set flag to restore modal on welcome page
                sessionStorage.setItem(SessionConstants.RESTORE_PASSWORD_MODAL, 'true');
            }
            
            // Navigate to welcome page
            const href = backButton.getAttribute('href');
            if (href) {
                window.location.href = href;
            }
        });
    }
    
    // Handle AJAX form submission
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    if (forgotPasswordForm && makeRequest) {
        forgotPasswordForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            
            const form = event.target;
            const submitButton = form.querySelector('button[type="submit"]');
            const emailField = form.querySelector('input[name="email"]');
            const originalButtonText = submitButton?.textContent || '';
            
            // Disable submit button
            if (submitButton) {
                submitButton.disabled = true;
                const loadingText = getTranslation?.('account.sending', 'Sending...') || 'Sending...';
                submitButton.textContent = submitButton.dataset?.loadingText || loadingText;
            }
            
            // Clear previous errors
            if (emailField) {
                emailField.classList.remove('is-invalid');
                const errorFeedback = document.getElementById('email_error');
                if (errorFeedback) {
                    errorFeedback.classList.add('d-none');
                    errorFeedback.textContent = '';
                }
            }
            
            // Prepare form data
            const formData = new FormData(form);
            
            // Helper function to reset button state
            const resetButton = () => {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText;
                }
            };
            
            // Helper function to show field error
            // Note: invalid-feedback element is always rendered in Blade template
            // JavaScript only toggles visibility and updates text (no DOM creation)
            const showFieldError = (message) => {
                if (emailField) {
                    emailField.classList.add('is-invalid');
                    const errorFeedback = document.getElementById('email_error');
                    if (errorFeedback) {
                        errorFeedback.textContent = message;
                        errorFeedback.classList.remove('d-none');
                    }
                }
            };
            
            // Track if we've already shown an error to prevent duplicate toasts
            let errorShown = false;
            
            // Make AJAX request using our custom utility function
            // makeRequest automatically handles CSRF token from meta tag or form input
            try {
                const response = await makeRequest(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    },
                    responseType: 'json'
                });
                
                // Extract data from response object
                const data = response.data || response; // Backward compatibility fallback
                resetButton();
                
                // Check if response indicates success
                if (data && (data.success || data.message || data.status)) {
                    // Show success toast
                    let message = data.message || data.status;
                    if (!message && getTranslation) {
                        message = getTranslation('account.password_reset_sent', 'Password reset link has been sent to your email.');
                    } else if (!message) {
                        message = 'Password reset link has been sent to your email.';
                    }
                    if (showToast) {
                        showToast(message, 'success');
                    }
                    
                    // Clear email field on success
                    if (emailField) {
                        emailField.value = '';
                    }
                }
            } catch (error) {
                resetButton();
                
                // Handle validation errors
                let errorMessage = 'An error occurred. Please try again.';
                if (error?.status === 422) {
                    try {
                        const errorData = error.response || (error.responseText ? JSON.parse(error.responseText) : {});
                        if (errorData.errors?.email) {
                            errorMessage = Array.isArray(errorData.errors.email) 
                                ? errorData.errors.email[0] 
                                : errorData.errors.email;
                            
                            if (showToast) {
                                showToast(errorMessage, 'error');
                            }
                            showFieldError(errorMessage);
                            errorShown = true;
                        } else if (errorData.message) {
                            errorMessage = errorData.message;
                            if (showToast) {
                                showToast(errorMessage, 'error');
                            }
                            errorShown = true;
                        } else {
                            errorMessage = getTranslation?.('account.validation_error', 'Validation error. Please check your input.') || 'Validation error. Please check your input.';
                            if (showToast) {
                                showToast(errorMessage, 'error');
                            }
                            errorShown = true;
                        }
                    } catch (e) {
                        // Only show error if we haven't already shown one
                        if (!errorShown) {
                            errorMessage = getTranslation?.('messages.error_occurred', errorMessage) || errorMessage;
                            if (showToast) {
                                showToast(errorMessage, 'error');
                            }
                            errorShown = true;
                        }
                    }
                } else {
                    // Show generic error only if we haven't already shown a specific one
                    if (!errorShown) {
                        if (error?.message) {
                            errorMessage = error.message;
                        }
                        if (showToast) {
                            showToast(errorMessage, 'error');
                        }
                        errorShown = true;
                    }
                }
            }
            
            return false;
        });
    }
});
