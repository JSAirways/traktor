/**
 * Admin access modal JavaScript.
 * Supports password auth and, when enabled, a 4-digit admin PIN with password fallback.
 */

import { setupModalAccessibility, setupModalFocus, setupModalInputReset, openModal } from '../../core/modal-utils.js';
import { isFullscreen, exitFullscreen, makeRequest, getTranslation, updateCsrfToken } from '../../core/utils.js';
import { LoadingStateManager } from '../../core/loading-state-manager.js';
import { initializePinInput } from '../../core/pin-input.js';

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

    setupModalAccessibility?.('adminPasswordModal');
    setupModalFocus?.('adminPasswordModal', '#adminPassword', 100);
    setupModalFocus?.('adminPasswordModal', '#adminPin', 100);
    setupModalInputReset?.('adminPasswordModal', 'adminPassword');

    const adminPasswordForm = document.getElementById('adminPasswordForm');
    const adminPinInput = document.getElementById('adminPin');
    const adminPinPanel = document.getElementById('adminAccessPinPanel');
    const adminPasswordPanel = document.getElementById('adminAccessPasswordPanel');
    const showPasswordFallbackButton = document.getElementById('showAdminPasswordFallback');
    const showPinPanelButton = document.getElementById('showAdminPinPanel');
    const pinLoadingManager = adminPinInput && LoadingStateManager ? new LoadingStateManager({
        loadingElementId: 'adminPinLoading',
        errorElementId: 'adminPinError',
        loadingShowClass: 'block',
        loadingHideClass: 'none',
        errorShowClass: 'block',
        errorHideClass: 'd-none',
    }) : null;

    function showPasswordError(message) {
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

    function resetPasswordError() {
        const passwordField = document.getElementById('adminPassword');
        const errorDiv = document.getElementById('adminPasswordError');
        passwordField?.classList.remove('is-invalid');
        if (errorDiv) {
            errorDiv.classList.add('d-none');
            errorDiv.textContent = '';
        }
    }

    function resetPinState() {
        if (adminPinInput) {
            adminPinInput.value = '';
            adminPinInput.disabled = false;
            adminPinInput.classList.remove('is-invalid');
        }
        pinLoadingManager?.reset();
    }

    function toggleAccessMode(mode) {
        const showPin = mode === 'pin';
        adminPinPanel?.classList.toggle('d-none', !showPin);
        adminPasswordPanel?.classList.toggle('d-none', showPin);
        resetPasswordError();
        resetPinState();
    }

    adminPasswordModal.addEventListener('show.bs.modal', async (event) => {
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

    adminPasswordModal.addEventListener('shown.bs.modal', () => {
        if (adminPinPanel && !adminPinPanel.classList.contains('d-none')) {
            adminPinInput?.focus();
            return;
        }
        document.getElementById('adminPassword')?.focus();
    });

    adminPasswordModal.addEventListener('hidden.bs.modal', () => {
        toggleAccessMode(adminPinPanel ? 'pin' : 'password');
    });

    showPasswordFallbackButton?.addEventListener('click', () => toggleAccessMode('password'));
    showPinPanelButton?.addEventListener('click', () => toggleAccessMode('pin'));

    if (adminPasswordForm && makeRequest) {
        adminPasswordForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const form = e.target;
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton?.textContent || '';

            resetPasswordError();
            if (submitButton) {
                submitButton.disabled = true;
                const loggingInText = getTranslation?.('auth.logging_in', 'Logging in...') || 'Logging in...';
                submitButton.textContent = loggingInText;
            }

            const formData = new FormData(form);

            try {
                const response = await makeRequest(form.action, {
                    method: 'POST',
                    body: formData,
                    responseType: 'json',
                    skipCsrf: true,
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const data = response.data || response; // Backward compatibility fallback
                if (data?.csrf_token) {
                    updateCsrfToken(data.csrf_token);
                }

                const redirectUrl = data?.redirect || data?.data?.redirect || '/admin';
                window.location.href = redirectUrl;
            } catch (error) {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText;
                }

                let errorMessage = 'An error occurred. Please try again.';
                if (error?.csrfRefreshFailed) {
                    errorMessage = error.message || 'Session expired. Please refresh the page and try again.';
                } else if (error?.status === 422) {
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
                } else if (error?.status === 0 || error?.message?.includes('Network')) {
                    errorMessage = getTranslation?.('messages.request_timeout', 'Network error. Please check your connection and try again.') || 'Network error. Please check your connection and try again.';
                } else {
                    if (error?.message) {
                        errorMessage = error.message;
                    } else {
                        errorMessage = getTranslation?.('common.error_occurred', errorMessage) || errorMessage;
                    }
                }

                showPasswordError(errorMessage);
            }
        });
    }

    if (adminPinInput) {
        const verifyAdminUrl = adminPasswordForm?.action || '/admin/verify-password';

        initializePinInput({
            input: adminPinInput,
            onReset: () => pinLoadingManager?.hideError(),
            onComplete: async (pin) => {
                resetPasswordError();
                pinLoadingManager?.showLoading();
                pinLoadingManager?.hideError();
                adminPinInput.disabled = true;

                try {
                    const response = await makeRequest(verifyAdminUrl, {
                        method: 'POST',
                        body: { pin },
                        responseType: 'json',
                        skipCsrf: true,
                        headers: { Accept: 'application/json' },
                    });

                    const data = response.data || response;
                    if (data?.csrf_token) {
                        updateCsrfToken(data.csrf_token);
                    }

                    window.location.href = data?.redirect || data?.data?.redirect || '/admin';
                } catch (error) {
                    adminPinInput.disabled = false;
                    pinLoadingManager?.hideLoading();
                    adminPinInput.value = '';
                    adminPinInput.classList.add('is-invalid');
                    adminPinInput.focus();

                    let errorMessage = getTranslation?.('auth.invalid_pin', 'Invalid PIN. Please try again.') || 'Invalid PIN. Please try again.';
                    try {
                        const errorData = error.responseData || (error.response ? JSON.parse(error.response) : {});
                        if (errorData.errors?.pin) {
                            errorMessage = Array.isArray(errorData.errors.pin) ? errorData.errors.pin[0] : errorData.errors.pin;
                        } else if (errorData.message) {
                            errorMessage = errorData.message;
                        }
                    } catch (_ignored) {
                    }

                    pinLoadingManager?.showError(errorMessage, 'auth.invalid_pin', errorMessage);
                }
            },
        });
    }

    const adminPasswordField = document.getElementById('adminPassword');
    if (adminPasswordField?.classList.contains('is-invalid')) {
        exitFullscreenAsync().then(() => {
            if (openModal) {
                openModal('adminPasswordModal', () => {
                    toggleAccessMode('password');
                    document.getElementById('adminPassword')?.focus();
                });
            }
        });
    }
});
