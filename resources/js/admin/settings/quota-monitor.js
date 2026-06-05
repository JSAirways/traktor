/**
 * YouTube API Quota Monitor
 * Fetches and displays quota usage data from Google Cloud Monitoring API
 * 
 * Note: This feature requires Google Cloud Monitoring API setup and QuotaController backend endpoint.
 * If the endpoint is not available, it will gracefully show an error message.
 */

import { makeRequest, formatNumber, getTranslation } from '../../core/utils.js';
import { LoadingStateManager } from '../../core/loading-state-manager.js';
import { errorHandler } from '../../core/error-handler.js';

class QuotaMonitor {
    constructor() {
        // Cache DOM elements
        this.refreshBtn = document.getElementById('refreshQuotaBtn');
        this.refreshBtnText = document.getElementById('refreshBtnText');
        this.quotaContent = document.getElementById('quotaContent');
        this.quotaUsed = document.getElementById('quotaUsed');
        this.quotaLimit = document.getElementById('quotaLimit');
        this.quotaRemaining = document.getElementById('quotaRemaining');
        this.quotaPercentage = document.getElementById('quotaPercentage');
        this.quotaProgressBar = document.getElementById('quotaProgressBar');
        this.quotaProgressText = document.getElementById('quotaProgressText');
        this.quotaTimestamp = document.getElementById('quotaTimestamp');
        
        // Initialize loading state manager
        if (LoadingStateManager) {
            this.loadingManager = new LoadingStateManager({
                loadingElementId: 'quotaLoading',
                errorElementId: 'quotaError',
                errorTextElementId: 'quotaErrorMessage'
            });
        } else {
            // Fallback if LoadingStateManager not available
            this.loadingManager = {
                showLoading: () => {},
                hideLoading: () => {},
                showError: () => {},
                hideError: () => {}
            };
        }
        
        this.isLoading = false;
        
        this.init();
    }
    
    init() {
        // Attach event listener to refresh button
        if (this.refreshBtn) {
            this.refreshBtn.addEventListener('click', () => {
                this.fetchQuotaData();
            });
        }
        
        // Fetch quota data on page load
        this.fetchQuotaData();
    }
    
    /**
     * Fetch quota data from API
     */
    async fetchQuotaData() {
        if (this.isLoading) {
            return;
        }
        
        // Check if makeRequest is available
        if (!makeRequest) {
            this.showError('Utility functions not available');
            return;
        }
        
        this.isLoading = true;
        this.showLoading();
        
        try {
            const response = await makeRequest('/admin/quota/stats', {
                method: 'GET',
                timeout: 15000
            });
            
            // Extract data from response object
            const data = response.data || response; // Backward compatibility fallback
            
            if (data?.success) {
                this.updateQuotaDisplay(data);
                this.loadingManager.hideError();
            } else {
                this.loadingManager.showError(
                    data?.message || 'Failed to fetch quota data',
                    'messages.quota_fetch_failed',
                    'Failed to fetch quota data'
                );
            }
        } catch (error) {
            // Try to extract error message from response
            let errorMessage = error?.message || 'Configuration error';
            if (error?.response) {
                try {
                    const errorData = typeof error.response === 'string' ? JSON.parse(error.response) : error.response;
                    if (errorData?.message) {
                        errorMessage = errorData.message;
                    }
                } catch (e) {
                    // Use default error message if parsing fails
                }
            }
            
            // Handle 404 gracefully (endpoint not implemented)
            if (error?.status === 404) {
                this.loadingManager.showError(
                    null,
                    'messages.quota_endpoint_not_available',
                    'Quota monitoring endpoint not available. Please ensure Google Cloud Monitoring API is configured.'
                );
            } else if (error?.status === 403) {
                // Authentication/authorization error - user might not be logged in or not admin
                // This can happen if session expired or user doesn't have admin access
                this.loadingManager.showError(
                    null,
                    'messages.unauthorized_access',
                    'Unauthorized access. Please refresh the page and try again.'
                );
            } else if (error?.status === 400) {
                // Log error for debugging
                console.log('Quota fetch error (400):', errorMessage);
                this.loadingManager.showError(
                    errorMessage,
                    'messages.quota_config_error',
                    errorMessage
                );
            } else if (error?.message === 'Request timeout') {
                if (errorHandler?.handleTimeoutError) {
                    errorHandler.handleTimeoutError(error, {
                        showToast: false,
                        logToConsole: true,
                        context: { action: 'fetchQuotaData' }
                    });
                }
                this.loadingManager.showError(null, 'messages.quota_timeout', 'Request timed out. Please try again.');
            } else if (error?.message === 'Network request failed') {
                if (errorHandler?.handleNetworkError) {
                    errorHandler.handleNetworkError(error, {
                        showToast: false,
                        logToConsole: true,
                        context: { action: 'fetchQuotaData' }
                    });
                }
                this.loadingManager.showError(null, 'messages.quota_network_error', 'Network error. Please check your connection.');
            } else {
                if (errorHandler?.handle) {
                    errorHandler.handle(error, {
                        showToast: false,
                        logToConsole: true,
                        context: { action: 'fetchQuotaData' }
                    });
                }
                this.loadingManager.showError(null, 'messages.quota_fetch_failed', 'Failed to fetch quota data. Please try again.');
            }
        } finally {
            this.isLoading = false;
            this.hideLoading();
        }
    }
    
    /**
     * Update quota display with fetched data
     */
    updateQuotaDisplay(response) {
        const used = response.used || 0;
        const limit = response.limit || 0;
        const remaining = response.remaining || 0;
        const percentage = response.percentage || 0;
        const timestamp = response.timestamp || null;
        
        // Update text values
        if (this.quotaUsed && formatNumber) {
            this.quotaUsed.textContent = formatNumber(used);
        }
        if (this.quotaLimit && formatNumber) {
            this.quotaLimit.textContent = formatNumber(limit);
        }
        if (this.quotaRemaining && formatNumber) {
            this.quotaRemaining.textContent = formatNumber(remaining);
        }
        if (this.quotaPercentage) {
            this.quotaPercentage.textContent = percentage.toFixed(2);
        }
        
        // Update progress bar
        if (this.quotaProgressBar) {
            this.quotaProgressBar.style.width = `${percentage}%`;
            this.quotaProgressBar.setAttribute('aria-valuenow', percentage);
        }
        if (this.quotaProgressText) {
            this.quotaProgressText.textContent = `${percentage.toFixed(1)}%`;
        }
        
        // Update progress bar color based on percentage
        if (this.quotaProgressBar) {
            this.quotaProgressBar.classList.remove('bg-success', 'bg-warning', 'bg-danger');
            if (percentage < 50) {
                this.quotaProgressBar.classList.add('bg-success');
            } else if (percentage < 80) {
                this.quotaProgressBar.classList.add('bg-warning');
            } else {
                this.quotaProgressBar.classList.add('bg-danger');
            }
        }
        
        // Update timestamp
        if (this.quotaTimestamp && timestamp) {
            this.quotaTimestamp.textContent = this.formatTimestamp(timestamp);
        }
    }
    
    /**
     * Show loading state
     */
    showLoading() {
        this.loadingManager.showLoading();
        if (this.quotaContent) {
            this.quotaContent.classList.add('d-none');
        }
        if (this.refreshBtn) {
            this.refreshBtn.disabled = true;
        }
        
        // Get translations from button text or translation
        if (this.refreshBtnText) {
            let refreshingText = this.refreshBtn?.getAttribute('data-refreshing-text');
            if (!refreshingText && getTranslation) {
                refreshingText = getTranslation('messages.quota_refreshing', 'Refreshing...');
            }
            if (refreshingText) {
                this.refreshBtnText.textContent = refreshingText;
            }
        }
    }
    
    /**
     * Hide loading state
     */
    hideLoading() {
        this.loadingManager.hideLoading();
        if (this.quotaContent) {
            this.quotaContent.classList.remove('d-none');
        }
        if (this.refreshBtn) {
            this.refreshBtn.disabled = false;
        }
        
        // Get translations from button text or translation
        if (this.refreshBtnText) {
            let refreshText = this.refreshBtn?.getAttribute('data-refresh-text');
            if (!refreshText && getTranslation) {
                refreshText = getTranslation('messages.quota_refresh', 'Refresh');
            }
            if (refreshText) {
                this.refreshBtnText.textContent = refreshText;
            }
        }
    }
    
    /**
     * Show error state
     */
    showError(message) {
        this.loadingManager.showError(
            message,
            'messages.quota_error',
            message || 'An error occurred'
        );
    }
    
    /**
     * Format timestamp to readable date/time
     */
    formatTimestamp(timestamp) {
        const date = new Date(timestamp * 1000);
        const now = new Date();
        const diffMinutes = Math.floor((now - date) / 60000);
        
        if (diffMinutes < 1) {
            return getTranslation?.('messages.quota_just_now', 'Just now') || 'Just now';
        } else if (diffMinutes < 60) {
            let minutesText = diffMinutes === 1 ? '1 minute ago' : `${diffMinutes} minutes ago`;
            if (getTranslation) {
                const minutesKey = 'messages.minutes_ago';
                minutesText = getTranslation(minutesKey, minutesText);
                minutesText = minutesText.replace(':count', diffMinutes);
            }
            return minutesText;
        } else if (diffMinutes < 1440) {
            const hours = Math.floor(diffMinutes / 60);
            let hoursText = hours === 1 ? '1 hour ago' : `${hours} hours ago`;
            if (getTranslation) {
                const hoursKey = 'messages.hours_ago';
                hoursText = getTranslation(hoursKey, hoursText);
                hoursText = hoursText.replace(':count', hours);
            }
            return hoursText;
        } else {
            // Format as date/time
            const options = {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            return date.toLocaleDateString(undefined, options);
        }
    }
}

// Initialize quota monitor when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new QuotaMonitor();
    });
} else {
    new QuotaMonitor();
}

// Export to global namespace for backward compatibility
if (typeof window !== 'undefined') {
    if (!window.Traktor) {
        window.Traktor = {};
    }
    if (!window.Traktor.Admin) {
        window.Traktor.Admin = {};
    }
    window.Traktor.Admin.quotaMonitor = QuotaMonitor;
}

export { QuotaMonitor };
