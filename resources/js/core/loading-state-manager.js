/**
 * Loading State Manager
 * 
 * Centralized utility for managing loading and error states across the application.
 * Provides consistent patterns for showing/hiding loading spinners and error messages.
 */

import { toggleVisibility, updateElementText, getTranslation } from './utils.js';

/**
 * LoadingStateManager class for managing loading and error states
 * 
 * @example
 * const loadingManager = new LoadingStateManager({
 *     loadingElementId: 'loadingSpinner',
 *     errorElementId: 'errorMessage',
 *     errorTextElementId: 'errorText'
 * });
 * 
 * loadingManager.showLoading();
 * loadingManager.hideLoading();
 * loadingManager.showError('Something went wrong');
 * loadingManager.hideError();
 */
export class LoadingStateManager {
    constructor(config = {}) {
        this.loadingElementId = config.loadingElementId || 'loadingSpinner';
        this.errorElementId = config.errorElementId || null;
        this.errorTextElementId = config.errorTextElementId || config.errorElementId || null;
        this.loadingShowClass = config.loadingShowClass || 'd-flex';
        this.loadingHideClass = config.loadingHideClass || 'd-none';
        this.errorShowClass = config.errorShowClass || 'd-block';
        this.errorHideClass = config.errorHideClass || 'd-none';
        
        this.isLoading = false;
    }
    
    /**
     * Show loading spinner
     * @returns {void}
     */
    showLoading() {
        this.isLoading = true;
        if (toggleVisibility) {
            toggleVisibility(this.loadingElementId, true, this.loadingShowClass, this.loadingHideClass);
        }
    }
    
    /**
     * Hide loading spinner
     * @returns {void}
     */
    hideLoading() {
        this.isLoading = false;
        if (toggleVisibility) {
            toggleVisibility(this.loadingElementId, false, this.loadingShowClass, this.loadingHideClass);
        }
    }
    
    /**
     * Show error message
     * @param {string} message - Error message to display
     * @param {string} [translationKey] - Translation key for error message
     * @param {string} [fallback] - Fallback message if translation not found
     * @returns {void}
     */
    showError(message = null, translationKey = null, fallback = null) {
        if (!this.errorElementId) return;
        
        // Get error message
        let errorMessage = message;
        if (!errorMessage && translationKey && getTranslation) {
            errorMessage = getTranslation(translationKey, fallback || 'An error occurred. Please try again.');
        } else if (!errorMessage) {
            errorMessage = fallback || 'An error occurred. Please try again.';
        }
        
        // Update error text if separate element exists
        if (this.errorTextElementId && this.errorTextElementId !== this.errorElementId && updateElementText) {
            updateElementText(this.errorTextElementId, errorMessage);
        }
        
        // Show error container
        if (toggleVisibility) {
            toggleVisibility(this.errorElementId, true, this.errorShowClass, this.errorHideClass);
        }
        
        // If error text is in the same element, update it
        if (this.errorTextElementId === this.errorElementId && updateElementText) {
            updateElementText(this.errorElementId, errorMessage);
        }
    }
    
    /**
     * Hide error message
     * @returns {void}
     */
    hideError() {
        if (!this.errorElementId) return;
        
        if (toggleVisibility) {
            toggleVisibility(this.errorElementId, false, this.errorShowClass, this.errorHideClass);
        }
        
        // Clear error text if separate element exists
        if (this.errorTextElementId && this.errorTextElementId !== this.errorElementId && updateElementText) {
            updateElementText(this.errorTextElementId, '');
        } else if (this.errorTextElementId === this.errorElementId && updateElementText) {
            updateElementText(this.errorElementId, '');
        }
    }
    
    /**
     * Reset both loading and error states
     * @returns {void}
     */
    reset() {
        this.hideLoading();
        this.hideError();
    }
}
