/**
 * Modal Utilities
 * Shared functions for modal initialization and management
 */

/**
 * Creates a Bootstrap modal instance
 * @param {string} modalId - The ID of the modal element
 * @param {object} options - Bootstrap Modal options (backdrop, keyboard, focus)
 * @returns {bootstrap.Modal|null} Modal instance or null if not available
 */
export function createModalInstance(modalId, options = {}) {
    const modalElement = document.getElementById(modalId);
    if (!modalElement) {
        return null;
    }
    
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return null;
    }
    
    const defaultOptions = {
        backdrop: true,
        keyboard: true,
        focus: true
    };
    
    return new bootstrap.Modal(modalElement, { ...defaultOptions, ...options });
}

/**
 * Sets up form reset when modal is shown
 * @param {string} modalId - The ID of the modal element
 * @param {string} formId - The ID of the form to reset
 * @param {boolean} preserveErrors - Whether to preserve validation errors
 */
export function setupModalFormReset(modalId, formId, preserveErrors = false) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    modal.addEventListener('show.bs.modal', () => {
        const form = document.getElementById(formId);
        if (!form) return;
        
        const inputs = form.querySelectorAll('input, textarea, select');
        for (const input of inputs) {
            // Don't clear if preserving errors and field has errors
            if (preserveErrors && input.classList.contains('is-invalid')) {
                continue;
            }
            
            // Clear value for text inputs, reset others
            if (input.type === 'text' || input.type === 'password' || input.type === 'email') {
                input.value = '';
            } else if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
            }
            
            // Remove invalid state
            input.classList.remove('is-invalid');
        }
        
        // Hide error messages (only if not preserving errors or field has no errors)
        const errorMessages = form.querySelectorAll('.invalid-feedback');
        for (const msg of errorMessages) {
            const forAttr = msg.getAttribute('for') || '';
            const relatedInput = form.querySelector(`#${forAttr}`);
            const hasErrors = relatedInput && relatedInput.classList.contains('is-invalid');
            
            if (!preserveErrors || !hasErrors) {
                if (!msg.classList.contains('d-block')) {
                    msg.classList.add('d-none');
                    msg.textContent = '';
                }
            }
        }
    }, { once: false });
}

/**
 * Sets up form reset with error preservation for a specific input field
 * Used when validation errors should be preserved (e.g., server-side validation errors)
 * @param {string} modalId - The ID of the modal element
 * @param {string} inputId - The ID of the input field to reset
 * @param {string} errorContainerId - Optional ID of error container element
 */
export function setupModalInputReset(modalId, inputId, errorContainerId = null) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    modal.addEventListener('show.bs.modal', () => {
        const input = document.getElementById(inputId);
        if (!input) return;
        
        // Only clear value if there are no validation errors
        const hasErrors = input.classList.contains('is-invalid');
        if (!hasErrors) {
            input.value = '';
            input.classList.remove('is-invalid');
        }
        
        // Hide error messages if no errors
        if (errorContainerId) {
            const errorDiv = document.getElementById(errorContainerId);
            if (errorDiv && !hasErrors) {
                errorDiv.classList.add('d-none');
                errorDiv.textContent = '';
            }
        } else {
            // Try to find error div within modal
            const errorDiv = modal.querySelector('.invalid-feedback');
            if (errorDiv && !hasErrors) {
                errorDiv.classList.add('d-none');
                errorDiv.textContent = '';
            }
        }
    }, { once: false });
}

/**
 * Sets up focus management when modal is shown
 * 
 * Note: For most cases, use the `autofocus` attribute in HTML instead of this function.
 * This function is only needed for dynamic focus management (e.g., when focus target changes).
 * 
 * @param {string} modalId - The ID of the modal element
 * @param {string} focusSelector - CSS selector for element to focus
 * @param {number} delay - Delay in milliseconds before focusing (default: 150)
 */
export function setupModalFocus(modalId, focusSelector, delay = 150) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    modal.addEventListener('shown.bs.modal', () => {
        const element = modal.querySelector(focusSelector);
        if (element) {
            setTimeout(() => {
                element.focus();
            }, delay);
        }
    }, { once: false });
}

/**
 * Sets up focus restoration when modal is hidden
 * @param {string} modalId - The ID of the modal element
 * @param {HTMLElement|function} triggerElement - Element or function to get trigger element
 */
export function setupModalFocusRestore(modalId, triggerElement) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    modal.addEventListener('hidden.bs.modal', () => {
        let element = null;
        
        if (typeof triggerElement === 'function') {
            element = triggerElement();
        } else if (triggerElement instanceof HTMLElement) {
            element = triggerElement;
        }
        
        if (element && typeof element.focus === 'function') {
            setTimeout(() => {
                element.focus();
            }, 50);
        }
    }, { once: false });
}

/**
 * Sets up accessibility focus management (blur before hide)
 * @param {string} modalId - The ID of the modal element
 */
export function setupModalAccessibility(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    modal.addEventListener('hide.bs.modal', () => {
        // Blur any focused element inside the modal to prevent aria-hidden violation
        const focusedElement = modal.querySelector(':focus');
        if (focusedElement) {
            focusedElement.blur();
        }
        
        // Also blur any focused element outside the modal that might cause aria-hidden violations
        // This is important when modals are opened over interactive content (e.g., video players)
        const activeElement = document.activeElement;
        if (activeElement && activeElement !== document.body && !modal.contains(activeElement)) {
            // Only blur if the focused element is not inside the modal and not the body
            activeElement.blur();
        }
    }, { once: false });
}

/**
 * Opens a modal programmatically with optional callback and options
 * @param {string} modalId - The ID of the modal element
 * @param {function} onShown - Optional callback when modal is shown
 * @param {object} options - Optional Bootstrap Modal options (backdrop, keyboard, focus)
 * @returns {bootstrap.Modal|null} Modal instance or null
 */
export function openModal(modalId, onShown = null, options = {}) {
    const modalElement = document.getElementById(modalId);
    if (!modalElement) {
        return null;
    }
    
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return null;
    }
    
    const defaultOptions = {
        focus: true,
        backdrop: true,
        keyboard: true
    };
    
    const modal = new bootstrap.Modal(modalElement, { ...defaultOptions, ...options });
    
    if (onShown && typeof onShown === 'function') {
        modalElement.addEventListener('shown.bs.modal', onShown, { once: true });
    }
    
    modal.show();
    return modal;
}
