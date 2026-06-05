/**
 * Error Handler Utility
 * 
 * Centralized error handling with consistent logging and user-friendly messages.
 * Provides standardized error handling patterns across the application.
 */

import { getTranslation, showToast } from './utils.js';

/**
 * Error types for categorization
 */
export const ErrorType = {
    NETWORK: 'network',
    TIMEOUT: 'timeout',
    VALIDATION: 'validation',
    AUTHENTICATION: 'authentication',
    SERVER: 'server',
    UNKNOWN: 'unknown'
};

/**
 * Error Handler class for standardized error handling
 * 
 * @example
 * const errorHandler = new ErrorHandler();
 * 
 * someOperation().then(() => {
 *     // success
 * }).catch((error) => {
 *     errorHandler.handle(error, {
 *         showToast: true,
 *         logToConsole: true
 *     });
 * });
 */
export class ErrorHandler {
    /**
     * Handle an error with standardized logging and user messages
     * 
     * @param {Error|Object} error - Error object or error-like object
     * @param {Object} options - Handling options
     * @param {boolean} [options.showToast=true] - Whether to show toast notification
     * @param {boolean} [options.logToConsole=true] - Whether to log to console
     * @param {string} [options.toastType='error'] - Toast type (error, warning, info)
     * @param {string} [options.customMessage] - Custom error message (overrides default)
     * @param {string} [options.translationKey] - Translation key for error message
     * @param {string} [options.fallback] - Fallback message if translation not found
     * @param {Object} [options.context] - Additional context for logging
     * @returns {string} Error message that was displayed/logged
     */
    handle(error, options = {}) {
        const {
            showToast: shouldShowToast = true,
            logToConsole: shouldLog = true,
            toastType = 'error',
            customMessage = null,
            translationKey = null,
            fallback = null,
            context = {}
        } = options;
        
        // Determine error type and message
        const errorType = this._getErrorType(error);
        const errorMessage = this._getErrorMessage(error, errorType, customMessage, translationKey, fallback);
        
        // Log to console if enabled
        if (shouldLog) {
            this._logError(error, errorType, errorMessage, context);
        }
        
        // Show toast if enabled
        if (shouldShowToast && showToast) {
            showToast(errorMessage, toastType, 5000);
        }
        
        return errorMessage;
    }
    
    /**
     * Handle network errors specifically
     * 
     * @param {Error} error - Error object
     * @param {Object} [options={}] - Handling options
     * @returns {string} Error message
     */
    handleNetworkError(error, options = {}) {
        return this.handle(error, {
            ...options,
            translationKey: 'messages.network_error',
            fallback: 'Network error. Please check your connection and try again.'
        });
    }
    
    /**
     * Handle timeout errors specifically
     * 
     * @param {Error} error - Error object
     * @param {Object} [options={}] - Handling options
     * @returns {string} Error message
     */
    handleTimeoutError(error, options = {}) {
        return this.handle(error, {
            ...options,
            translationKey: 'messages.request_timeout',
            fallback: 'Request timed out. Please try again.'
        });
    }
    
    /**
     * Handle validation errors specifically
     * 
     * @param {Error|Object} error - Error object or validation error response
     * @param {Object} [options={}] - Handling options
     * @returns {string} Error message
     */
    handleValidationError(error, options = {}) {
        // Extract validation message from error response if available
        let message = null;
        if (error.response && typeof error.response === 'string') {
            try {
                const parsed = JSON.parse(error.response);
                if (parsed.errors) {
                    // Get first validation error
                    const errorValues = Object.values(parsed.errors);
                    const firstError = errorValues[0];
                    message = Array.isArray(firstError) ? firstError[0] : firstError;
                } else if (parsed.message) {
                    message = parsed.message;
                }
            } catch (e) {
                // Not JSON, use default
            }
        }
        
        return this.handle(error, {
            ...options,
            customMessage: message,
            translationKey: message ? null : 'messages.validation_error',
            fallback: message || 'Validation error. Please check your input and try again.'
        });
    }
    
    /**
     * Handle authentication errors specifically
     * 
     * @param {Error} error - Error object
     * @param {Object} [options={}] - Handling options
     * @returns {string} Error message
     */
    handleAuthenticationError(error, options = {}) {
        return this.handle(error, {
            ...options,
            translationKey: 'auth.authentication_failed',
            fallback: 'Authentication failed. Please try again.',
            toastType: 'warning'
        });
    }
    
    /**
     * Get error type from error object
     * 
     * @private
     * @param {Error|Object} error - Error object
     * @returns {string} Error type
     */
    _getErrorType(error) {
        if (!error) return ErrorType.UNKNOWN;
        
        // Check error message for common patterns
        const message = error.message || error.toString() || '';
        
        if (message.includes('timeout') || message === 'Request timeout') {
            return ErrorType.TIMEOUT;
        }
        
        if (message.includes('network') || message === 'Network request failed') {
            return ErrorType.NETWORK;
        }
        
        if (error.status === 422 || message.includes('validation')) {
            return ErrorType.VALIDATION;
        }
        
        if (error.status === 401 || error.status === 403) {
            return ErrorType.AUTHENTICATION;
        }
        
        if (error.status >= 500) {
            return ErrorType.SERVER;
        }
        
        return ErrorType.UNKNOWN;
    }
    
    /**
     * Get user-friendly error message
     * 
     * @private
     * @param {Error|Object} error - Error object
     * @param {string} errorType - Error type
     * @param {string|null} customMessage - Custom message override
     * @param {string|null} translationKey - Translation key
     * @param {string|null} fallback - Fallback message
     * @returns {string} Error message
     */
    _getErrorMessage(error, errorType, customMessage, translationKey, fallback) {
        // Use custom message if provided
        if (customMessage) {
            return customMessage;
        }
        
        // Use translation if provided
        if (translationKey && getTranslation) {
            const translated = getTranslation(translationKey, fallback);
            if (translated !== translationKey) {
                return translated;
            }
        }
        
        // Use fallback if provided
        if (fallback) {
            return fallback;
        }
        
        // Default messages by error type
        const defaultMessages = {
            [ErrorType.NETWORK]: 'Network error. Please check your connection and try again.',
            [ErrorType.TIMEOUT]: 'Request timed out. Please try again.',
            [ErrorType.VALIDATION]: 'Validation error. Please check your input and try again.',
            [ErrorType.AUTHENTICATION]: 'Authentication failed. Please try again.',
            [ErrorType.SERVER]: 'Server error. Please try again later.',
            [ErrorType.UNKNOWN]: 'An error occurred. Please try again.'
        };
        
        return defaultMessages[errorType] || defaultMessages[ErrorType.UNKNOWN];
    }
    
    /**
     * Log error to console with context
     * 
     * @private
     * @param {Error|Object} error - Error object
     * @param {string} errorType - Error type
     * @param {string} userMessage - User-friendly message
     * @param {Object} context - Additional context
     * @returns {void}
     */
    _logError(error, errorType, userMessage, context) {
        const errorObj = error instanceof Error ? {
            message: error.message,
            stack: error.stack,
            name: error.name
        } : error;
        
        const logData = {
            errorType: errorType,
            userMessage: userMessage,
            error: errorObj,
            context: context,
            timestamp: new Date().toISOString()
        };
        
        // Log with appropriate console method
        if (errorType === ErrorType.SERVER || errorType === ErrorType.UNKNOWN) {
            console.error('Error occurred:', logData);
        } else {
            console.warn('Error occurred:', logData);
        }
    }
}

/**
 * Default error handler instance
 */
export const errorHandler = new ErrorHandler();
