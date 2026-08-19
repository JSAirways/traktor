/**
 * Admin Forms JavaScript
 * Handles PIN toggle, PIN generation, cat GIF selection, and device checkbox management
 */

import { getElementJson, getDatasetJson } from '../../core/utils.js';

// PIN Field Toggle
function togglePinField(pinWrapperId, pinInputId, pinAsteriskId, usePinCheckboxId, currentPin, pinName = 'pin') {
    const usePin = document.getElementById(usePinCheckboxId)?.checked;
    const pinWrapper = document.getElementById(pinWrapperId);
    const pinInput = document.getElementById(pinInputId);
    const pinAsterisk = document.getElementById(pinAsteriskId);
    
    if (usePin) {
        if (pinWrapper) pinWrapper.style.display = '';
        if (pinInput) {
            pinInput.setAttribute('name', pinName);
            pinInput.setAttribute('required', 'required');
            // If PIN field is empty and we have a current PIN, restore it
            if (!pinInput.value && currentPin) {
                pinInput.value = currentPin;
            }
        }
        if (pinAsterisk) pinAsterisk.style.display = 'inline';
    } else {
        if (pinWrapper) pinWrapper.style.display = 'none';
        if (pinInput) {
            pinInput.removeAttribute('name');
            pinInput.removeAttribute('required');
        }
        if (pinAsterisk) pinAsterisk.style.display = 'none';
    }
}

// Generate PIN
function generatePin(pinInputId, usePinCheckboxId, pinName = 'pin') {
    const pin = Math.floor(1000 + Math.random() * 9000).toString();
    const pinInput = document.getElementById(pinInputId);
    if (pinInput) {
        pinInput.value = pin;
        // Ensure name attribute is set if toggle is on
        const usePinCheckbox = document.getElementById(usePinCheckboxId);
        if (usePinCheckbox?.checked) {
            pinInput.setAttribute('name', pinName);
        }
    }
}

// Cat GIF Selection
function selectCatGif(value, hiddenInputId) {
    const hiddenInput = document.getElementById(hiddenInputId);
    if (hiddenInput) {
        hiddenInput.value = value;
    }
    
    // Update visual selection
    const options = document.querySelectorAll('.cat-gif-option');
    for (const option of options) {
        option.classList.remove('selected');
    }
}

// Device Checkbox Management
function selectAllDevices() {
    const checkboxes = document.querySelectorAll('.device-checkbox');
    for (const checkbox of checkboxes) {
        checkbox.checked = true;
    }
}

function deselectAllDevices() {
    const checkboxes = document.querySelectorAll('.device-checkbox');
    for (const checkbox of checkboxes) {
        checkbox.checked = false;
    }
}

// Initialize PIN fields on page load
function initializePinFields() {
    if (!getElementJson) return;
    
    const pinFields = document.querySelectorAll('script[data-pin-field]');
    for (const field of pinFields) {
        const config = getElementJson(field, {});
        if (config?.usePinCheckboxId) {
            const usePinCheckbox = document.getElementById(config.usePinCheckboxId);
            if (usePinCheckbox) {
                // Set initial state
                togglePinField(
                    config.pinWrapperId,
                    config.pinInputId,
                    config.pinAsteriskId,
                    config.usePinCheckboxId,
                    config.currentPin || '',
                    config.pinName || 'pin'
                );
            }
        }
    }
}

// Initialize cat GIF selectors
function initializeCatGifSelectors() {
    if (!getDatasetJson) return;
    
    const catGifSelectors = document.querySelectorAll('[data-cat-gif-selector]');
    for (const selector of catGifSelectors) {
        const config = getDatasetJson(selector, 'catGifSelector', {});
        if (config?.hiddenInputId) {
            const options = selector.querySelectorAll('.cat-gif-option');
            for (const option of options) {
                option.addEventListener('click', (e) => {
                    const value = option.dataset?.gifValue || '';
                    selectCatGif(value, config.hiddenInputId);
                    // Update visual selection
                    const allOptions = selector.querySelectorAll('.cat-gif-option');
                    for (const opt of allOptions) {
                        opt.classList.remove('selected');
                    }
                    option.classList.add('selected');
                });
            }
        }
    }
}

// Initialize device checkboxes with event delegation
function initializeDeviceCheckboxes() {
    // Use event delegation for device checkbox buttons
    document.addEventListener('click', (e) => {
        // Handle "Enable All" button
        if (e.target.matches('[data-action="select-all-devices"]') || 
            e.target.closest('[data-action="select-all-devices"]')) {
            e.preventDefault();
            selectAllDevices();
        }
        
        // Handle "Disable All" button
        if (e.target.matches('[data-action="deselect-all-devices"]') || 
            e.target.closest('[data-action="deselect-all-devices"]')) {
            e.preventDefault();
            deselectAllDevices();
        }
    });
}

// Initialize PIN field handlers with event delegation
function initializePinFieldHandlers() {
    if (!getDatasetJson) return;
    
    // Handle PIN toggle checkbox changes via event delegation
    document.addEventListener('change', (e) => {
        if (e.target.matches('[data-pin-toggle]')) {
            const config = getDatasetJson(e.target, 'pinToggle', {});
            if (config?.usePinCheckboxId) {
                togglePinField(
                    config.pinWrapperId,
                    config.pinInputId,
                    config.pinAsteriskId,
                    config.usePinCheckboxId,
                    config.currentPin || '',
                    config.pinName || 'pin'
                );
            }
        }
    });
    
    // Handle PIN generate button clicks via event delegation
    document.addEventListener('click', (e) => {
        if (e.target.matches('[data-generate-pin]') || 
            e.target.closest('[data-generate-pin]')) {
            e.preventDefault();
            const button = e.target.closest('[data-generate-pin]') || e.target;
            const config = getDatasetJson(button, 'generatePin', {});
            if (config?.pinInputId) {
                generatePin(config.pinInputId, config.usePinCheckboxId, config.pinName || 'pin');
            }
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    initializePinFields();
    initializePinFieldHandlers();
    initializeCatGifSelectors();
    initializeDeviceCheckboxes();
});

export { togglePinField, generatePin, selectCatGif, selectAllDevices, deselectAllDevices };
