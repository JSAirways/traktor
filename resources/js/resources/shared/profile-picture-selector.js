/**
 * Profile Picture Selector JavaScript
 * Clickable avatar opens a modal; selecting an option updates the form + preview.
 */

import { getDatasetJson } from '../../core/utils.js';

/**
 * @param {string} value
 * @param {string} hiddenInputId
 */
function selectCatGif(value, hiddenInputId) {
    const hiddenInput = document.getElementById(hiddenInputId);
    if (hiddenInput) {
        hiddenInput.value = value;
    }
}

/**
 * @param {HTMLElement} trigger
 * @param {string} value
 * @param {string} assetBase
 */
function updateTriggerPreview(trigger, value, assetBase) {
    if (!trigger) return;
    const img = trigger.querySelector('.profile-picture-trigger-img');
    const placeholder = trigger.querySelector('.profile-picture-trigger-placeholder');
    if (!img || !placeholder) return;

    if (value) {
        img.src = `${assetBase.replace(/\/$/, '')}/${value}`;
        img.classList.remove('d-none');
        placeholder.classList.add('d-none');
    } else {
        img.removeAttribute('src');
        img.classList.add('d-none');
        placeholder.classList.remove('d-none');
    }
}

/**
 * Move picker modals to document.body so parent filter/transform cannot trap them.
 */
function relocateProfilePictureModals() {
    const modals = document.querySelectorAll('.profile-picture-modal');
    for (const modal of modals) {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    }
}

/**
 * Initialize cat GIF / profile picture selectors
 */
function initializeCatGifSelectors() {
    relocateProfilePictureModals();

    const catGifSelectors = document.querySelectorAll('[data-cat-gif-selector]');
    for (const selector of catGifSelectors) {
        if (selector.hasAttribute('data-handler-attached')) continue;
        selector.setAttribute('data-handler-attached', 'true');

        const config = getDatasetJson?.(selector, 'catGifSelector', {}) || {};
        if (!config?.hiddenInputId) continue;

        const options = selector.querySelectorAll('.cat-gif-option');
        for (const option of options) {
            option.addEventListener('click', () => {
                const value = option.dataset?.gifValue || '';
                selectCatGif(value, config.hiddenInputId);

                const allOptions = selector.querySelectorAll('.cat-gif-option');
                for (const opt of allOptions) {
                    opt.classList.remove('selected');
                }
                option.classList.add('selected');

                const trigger = config.triggerId
                    ? document.getElementById(config.triggerId)
                    : null;
                updateTriggerPreview(trigger, value, config.assetBase || '');

                if (config.modalId && window.bootstrap?.Modal) {
                    const modalEl = document.getElementById(config.modalId);
                    if (modalEl) {
                        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    }
                }
            });
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initializeCatGifSelectors();
});

export { initializeCatGifSelectors, selectCatGif, updateTriggerPreview };
