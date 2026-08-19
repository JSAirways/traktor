/**
 * PIN Entry Modal JavaScript
 * Handles PIN modal opening, auto-validation on 4 digits, and error handling.
 */

import { TimingConstants } from '../../core/constants.js';
import { LoadingStateManager } from '../../core/loading-state-manager.js';
import { setupModalAccessibility, setupModalFocus } from '../../core/modal-utils.js';
import { makeRequest, getCsrfToken } from '../../core/utils.js';
import { errorHandler } from '../../core/error-handler.js';
import { initializePinInput } from '../../core/pin-input.js';

const loadingManager = LoadingStateManager ? new LoadingStateManager({
    loadingElementId: 'pinEntryLoading',
    errorElementId: 'pinEntryError',
    loadingShowClass: 'block',
    loadingHideClass: 'none',
    errorShowClass: 'block',
    errorHideClass: 'd-none',
}) : null;

let pinInput = null;
let usernameInput = null;
let currentRedirectUrl = null;

async function validatePin(pin) {
    if (!pinInput || !usernameInput) {
        return;
    }

    const username = usernameInput.value;
    if (!username || pin.length !== 4) {
        return;
    }

    pinInput.disabled = true;
    loadingManager?.showLoading();
    loadingManager?.hideError();

    try {
        const requestBody = { username, pin };
        const redirectUrl = currentRedirectUrl || window.pinEntryRedirectUrl;
        if (redirectUrl) {
            requestBody.intended_url = redirectUrl;
        }

        const response = await makeRequest('/api/view/validate-pin', {
            method: 'POST',
            body: requestBody,
            headers: {
                'X-CSRF-TOKEN': getCsrfToken?.() || '',
            },
            responseType: 'json',
        });

        const data = response.data || response;
        loadingManager?.hideLoading();
        pinInput.disabled = false;

        if (data?.success) {
            const nextUrl = currentRedirectUrl || data.redirect_url || window.pinEntryRedirectUrl;
            if (nextUrl) {
                window.location.href = nextUrl;
            } else {
                window.location.reload();
            }
            return;
        }

        throw new Error(data?.message || 'Invalid PIN. Please try again.');
    } catch (error) {
        loadingManager?.hideLoading();
        pinInput.disabled = false;

        if (errorHandler?.handleTimeoutError) {
            errorHandler.handleTimeoutError(error, {
                showToast: false,
                logToConsole: true,
                context: { action: 'validatePin' },
            });
        } else if (errorHandler?.handle) {
            errorHandler.handle(error, {
                showToast: false,
                logToConsole: true,
                context: { action: 'validatePin' },
            });
        }

        pinInput.value = '';
        pinInput.classList.add('is-invalid');
        pinInput.focus();
        loadingManager?.showError(error?.message || null, 'auth.invalid_pin', 'Invalid PIN. Please try again.');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const pinEntryModal = document.getElementById('pinEntryModal');
    if (!pinEntryModal) return;

    pinInput = document.getElementById('pinEntryPin');
    usernameInput = document.getElementById('pinEntryUsername');

    setupModalAccessibility?.('pinEntryModal');
    if (TimingConstants) {
        setupModalFocus?.('pinEntryModal', '#pinEntryPin', TimingConstants.MODAL_FOCUS_DELAY);
    }

    pinEntryModal.addEventListener('show.bs.modal', (event) => {
        const triggerButton = event.relatedTarget;
        let username = null;
        let redirectUrl = null;

        if (triggerButton) {
            username = triggerButton.getAttribute('data-child-username');
            const slug = triggerButton.getAttribute('data-child-slug');
            redirectUrl = window.pinEntryRedirectUrl || (slug ? `/${slug}/gallery` : null);
        } else {
            const scriptTag = document.querySelector('script[data-pin-username]');
            username = scriptTag?.getAttribute('data-pin-username') || null;
            redirectUrl = window.pinEntryRedirectUrl;
        }

        if (usernameInput) {
            usernameInput.value = username || '';
        }
        currentRedirectUrl = redirectUrl || null;
        pinInput?.classList.remove('is-invalid');
        loadingManager?.reset();
    });

    pinEntryModal.addEventListener('hidden.bs.modal', () => {
        currentRedirectUrl = null;
        pinInput?.classList.remove('is-invalid');
        if (pinInput) {
            pinInput.value = '';
            pinInput.disabled = false;
        }
        loadingManager?.reset();
    });

    if (pinInput) {
        initializePinInput({
            input: pinInput,
            onComplete: validatePin,
            onReset: () => loadingManager?.hideError(),
        });
    }
});

function openPinEntryModal(username, redirectUrl = null) {
    const modal = document.getElementById('pinEntryModal');
    if (!modal) return;

    const usernameInputEl = document.getElementById('pinEntryUsername');
    if (usernameInputEl) {
        usernameInputEl.value = username;
    }

    if (redirectUrl) {
        currentRedirectUrl = redirectUrl;
        window.pinEntryRedirectUrl = redirectUrl;
    }

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

export { validatePin, openPinEntryModal };

if (typeof window !== 'undefined') {
    if (!window.Traktor) {
        window.Traktor = {};
    }
    window.Traktor.openPinEntryModal = openPinEntryModal;
}
