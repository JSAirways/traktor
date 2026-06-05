/**
 * Profile Picture Selector JavaScript
 * Handles profile picture selection for frontend pages
 * Extracted from admin-forms.js for reuse
 */

import { getDatasetJson } from '../../core/utils.js';

/**
 * Select a cat GIF and update the hidden input
 * @param {string} value - The GIF filename value
 * @param {string} hiddenInputId - The ID of the hidden input to update
 */
function selectCatGif(value, hiddenInputId) {
    const hiddenInput = document.getElementById(hiddenInputId);
    if (hiddenInput) {
        hiddenInput.value = value;
    }
}

/**
 * Initialize cat GIF selectors
 */
function initializeCatGifSelectors() {
    const catGifSelectors = document.querySelectorAll('[data-cat-gif-selector]');
    for (const selector of catGifSelectors) {
        const config = getDatasetJson?.(selector, 'catGifSelector', {}) || {};
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

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    initializeCatGifSelectors();
});

export { initializeCatGifSelectors, selectCatGif };
