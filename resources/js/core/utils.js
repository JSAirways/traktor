/**
 * Utility Functions
 * Core utility functions for the application
 */

/**
 * Checks if currently in fullscreen mode
 * @returns {boolean} True if in fullscreen, false otherwise
 */
export function isFullscreen() {
    return !!(document.fullscreenElement || document.webkitFullscreenElement || 
              document.mozFullScreenElement || document.msFullscreenElement);
}

/**
 * Exits fullscreen mode
 * @returns {void}
 */
export function exitFullscreen() {
    if (document.exitFullscreen) {
        document.exitFullscreen();
    } else if (document.webkitExitFullscreen) {
        document.webkitExitFullscreen();
    } else if (document.mozCancelFullScreen) {
        document.mozCancelFullScreen();
    } else if (document.msExitFullscreen) {
        document.msExitFullscreen();
    }
}

/**
 * Checks if fullscreen API is supported by the browser
 * PS4 browser supports fullscreen API
 * @returns {boolean} True if fullscreen is supported, false otherwise
 */
export function isFullscreenSupported() {
    // Check if any fullscreen API method exists
    return !!(document.documentElement.requestFullscreen || 
                                   document.documentElement.webkitRequestFullscreen || 
                                   document.documentElement.mozRequestFullScreen || 
                                   document.documentElement.msRequestFullscreen);
}

/**
 * Format time in seconds to MM:SS or H:MM:SS format
 */
export function formatTime(seconds) {
    if (!seconds || isNaN(seconds)) return '0:00';
    
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    
    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
    return `${minutes}:${String(secs).padStart(2, '0')}`;
}

/**
 * Format duration in seconds to HH:MM:SS format (for consistency with Blade template)
 */
export function formatDuration(seconds) {
    if (!seconds || isNaN(seconds)) return '00:00:00';
    
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

/**
 * Toggle element visibility
 */
export function toggleElementVisibility(elementId, show) {
    const element = document.getElementById(elementId);
    if (element) {
        if (show) {
            element.classList.remove('d-none');
        } else {
            element.classList.add('d-none');
        }
    }
}

/**
 * Prevent/restore horizontal scrolling for player view
 */
export function setPlayerViewOverflow(prevent) {
    if (prevent) {
        document.body.style.overflowX = 'hidden';
        document.documentElement.style.overflowX = 'hidden';
    } else {
        document.body.style.overflowX = '';
        document.documentElement.style.overflowX = '';
    }
}

/**
 * Check if player view is currently visible
 */
export function isPlayerViewVisible() {
    const playerView = document.querySelector('.player-view');
    return playerView && !playerView.classList.contains('d-none');
}

/**
 * Fix mobile viewport height for browsers that don't support dvh
 * Sets CSS custom property --vh based on actual viewport height
 * This accounts for mobile browser address bars that reduce visible viewport
 */
let viewportFixInitialized = false;

export function fixMobileViewport() {
    const playerView = document.querySelector('.player-view');
    if (!playerView || playerView.classList.contains('d-none')) {
        return;
    }
    
    // Function to update viewport height
    const updateViewportHeight = () => {
        const playerView = document.querySelector('.player-view');
        // Only update if player view is visible
        if (!playerView || playerView.classList.contains('d-none')) {
            return;
        }
        
        let vh;
        let viewportHeight;
        
        // Use Visual Viewport API if available (better for mobile browsers)
        if (window.visualViewport) {
            vh = window.visualViewport.height * 0.01;
            viewportHeight = window.visualViewport.height;
        } else {
            // Fallback to window.innerHeight
            vh = window.innerHeight * 0.01;
            viewportHeight = window.innerHeight;
        }
        
        // Set CSS custom property (for player page, this will be used with navbar offset)
        document.documentElement.style.setProperty('--vh', `${vh}px`);
        
        // Also ensure video container is visible and properly sized
        // CSS handles flexbox centering, JavaScript just ensures dimensions are set
        const videoContainer = document.getElementById('videoContainer');
        const customControlBar = document.getElementById('customControlBar');
        const playerElement = document.getElementById('player');
        if (videoContainer) {
            videoContainer.style.display = 'flex'; // CSS uses flexbox for centering
        }
        if (playerElement) {
            // Player element is sized by CSS, just ensure it's visible
            playerElement.style.display = 'block';
            playerElement.style.visibility = 'visible';
        }
        // Ensure control bar is visible (class toggling only - following best practices)
        if (customControlBar) {
            customControlBar.classList.remove('hidden');
        }
    };
    
    // Update immediately
    updateViewportHeight();
    
    // Set up event listeners only once
    if (!viewportFixInitialized) {
        // Listen for viewport resize events (browser UI show/hide)
        window.addEventListener('resize', updateViewportHeight);
        
        // Use Visual Viewport API if available (more accurate for mobile)
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', updateViewportHeight);
        }
        
        // Also update on orientation change
        window.addEventListener('orientationchange', () => {
            // Small delay to ensure accurate measurements after orientation change
            setTimeout(updateViewportHeight, 100);
        });
        
        viewportFixInitialized = true;
    }
}

/**
 * Get the toast container (should exist in Blade templates)
 * @returns {HTMLElement|null} The toast container element or null if not found
 */
export function getToastContainer() {
    // Toast container should exist in Blade templates - return it or null
    return document.getElementById('toastContainer');
}

/**
 * Makes an XMLHttpRequest with standardized error handling and configuration
 * @param {string} url - The URL to request
 * @param {object} options - Request options
 * @param {string} options.method - HTTP method (default: 'GET')
 * @param {object|FormData|string} options.body - Request body (object will be JSON stringified, FormData sent as-is, string sent as-is)
 * @param {object} options.headers - Additional headers to set (CSRF token, Accept, etc.)
 * @param {string} options.responseType - Expected response type: 'json', 'text', 'html' (default: 'json')
 * @param {number} options.timeout - Request timeout in milliseconds (default: 10000)
 * @param {boolean} options.skipCsrf - If true, skip adding CSRF token header (for routes excluded from CSRF protection)
 * @param {function} options.onSuccess - Optional success callback (data, xhr)
 * @param {function} options.onError - Optional error callback (error, xhr)
 * @returns {Promise} Promise that resolves with response data or rejects with error
 */
export function makeRequest(url, options = {}) {
    const method = options.method || 'GET';
    const body = options.body || null;
    const headers = options.headers || {};
    const responseType = options.responseType || 'json';
    const timeout = options.timeout || 10000;
    const skipCsrf = options.skipCsrf || false;
    const retryCount = options.retryCount || 0; // Track retry attempts to prevent infinite loops
    const onSuccess = options.onSuccess || null;
    const onError = options.onError || null;
    
    // Prevent infinite retry loops (max 1 retry for CSRF)
    if (retryCount > 1) {
        const error = new Error('Request failed after retry. Please refresh the page.');
        error.status = 419;
        if (onError) {
            onError(error, null);
        }
        return Promise.reject(error);
    }
    
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        
        // Ensure cookies are sent with the request (needed for session)
        xhr.withCredentials = true;
        
        // Set default headers
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        // Determine content type and accept headers based on body type
        if (body instanceof FormData) {
            // FormData - don't set Content-Type, browser will set it with boundary
            xhr.setRequestHeader('Accept', headers.Accept || 'application/json');
        } else if (typeof body === 'object' && body !== null) {
            // Object - send as JSON
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('Accept', headers.Accept || 'application/json');
        } else if (typeof body === 'string') {
            // String - send as-is, use provided Content-Type or default to text/plain
            xhr.setRequestHeader('Content-Type', headers['Content-Type'] || 'text/plain');
            xhr.setRequestHeader('Accept', headers.Accept || 'application/json');
        } else {
            // No body - default headers
            xhr.setRequestHeader('Accept', headers.Accept || 'application/json');
        }
        
        // Set CSRF token if not already in headers and not skipped
        // Always get fresh token (in case it was updated)
        if (!skipCsrf && !headers['X-CSRF-TOKEN'] && !headers['x-csrf-token']) {
            const csrfToken = getCsrfToken();
            if (csrfToken) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            } else if (method !== 'GET') {
                // Warn if CSRF token is missing for state-changing requests
                console.warn('CSRF token not found for request:', url, method);
            }
        }
        
        // Set custom headers (but skip CSRF-related headers if skipCsrf is true)
        Object.keys(headers).forEach(key => {
            const lowerKey = key.toLowerCase();
            // Skip CSRF-related headers if skipCsrf is true
            if (skipCsrf && (lowerKey === 'x-csrf-token' || lowerKey === 'csrf-token')) {
                return;
            }
            if (lowerKey !== 'accept' && lowerKey !== 'content-type') {
                xhr.setRequestHeader(key, headers[key]);
            }
        });
        
        // Set timeout
        xhr.timeout = timeout;
        
        // Handle response
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    let data;
                    if (responseType === 'json') {
                        data = JSON.parse(xhr.responseText);
                    } else if (responseType === 'html' || responseType === 'text') {
                        data = xhr.responseText;
                    } else {
                        data = xhr.responseText;
                    }
                    
                    // Extract all response headers
                    const responseHeaders = {};
                    const headerString = xhr.getAllResponseHeaders();
                    if (headerString) {
                        const headerPairs = headerString.trim().split('\r\n');
                        for (const headerPair of headerPairs) {
                            const [name, ...valueParts] = headerPair.split(': ');
                            const headerName = name.toLowerCase();
                            const headerValue = valueParts.join(': ');
                            responseHeaders[headerName] = headerValue;
                        }
                    }
                    
                    // Build response object with data, headers, and xhr
                    const response = {
                        data: data,
                        headers: responseHeaders,
                        xhr: xhr
                    };
                    
                    if (onSuccess) {
                        onSuccess(data, xhr);
                    }
                    
                    // Return object with data, headers, and xhr for consistent API
                    resolve(response);
                } catch (e) {
                    const error = new Error('Invalid response format');
                    if (onError) {
                        onError(error, xhr);
                    }
                    reject(error);
                }
            } else {
                // Handle CSRF token mismatch (419) - automatically refresh and retry once
                if (xhr.status === 419 && !skipCsrf && method !== 'GET' && retryCount === 0) {
                    // Refresh token and retry the request
                    // Note: refreshCsrfToken is defined in this file, so we can call it directly
                    // We'll use a small delay to ensure the function is available
                    setTimeout(() => {
                        // Get fresh token from server
                        const refreshXhr = new XMLHttpRequest();
                        refreshXhr.open('GET', '/csrf-token', true);
                        refreshXhr.withCredentials = true;
                        refreshXhr.setRequestHeader('Accept', 'application/json');
                        
                        refreshXhr.onload = () => {
                            if (refreshXhr.status >= 200 && refreshXhr.status < 300) {
                                try {
                                    const refreshData = JSON.parse(refreshXhr.responseText);
                                    const newToken = refreshData?.token || refreshData?.data?.token;
                                    
                                    if (newToken) {
                                        // Update token in DOM
                                        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                                        if (csrfMeta) {
                                            csrfMeta.content = newToken;
                                        }
                                        const csrfInputs = document.querySelectorAll('input[name="_token"]');
                                        csrfInputs.forEach(input => {
                                            input.value = newToken;
                                        });
                                        
                                        // Retry the original request with new token
                                        makeRequest(url, {
                                            ...options,
                                            skipCsrf: false,
                                            retryCount: retryCount + 1
                                        }).then(resolve).catch(reject);
                                    } else {
                                        // Token refresh failed
                                        const error = new Error('Session expired. Please refresh the page and try again.');
                                        error.status = 419;
                                        error.response = xhr.responseText;
                                        error.csrfRefreshFailed = true;
                                        
                                        if (onError) {
                                            onError(error, xhr);
                                        }
                                        reject(error);
                                    }
                                } catch (e) {
                                    const error = new Error('Session expired. Please refresh the page and try again.');
                                    error.status = 419;
                                    error.response = xhr.responseText;
                                    error.csrfRefreshFailed = true;
                                    
                                    if (onError) {
                                        onError(error, xhr);
                                    }
                                    reject(error);
                                }
                            } else {
                                // Token refresh request failed
                                const error = new Error('Session expired. Please refresh the page and try again.');
                                error.status = 419;
                                error.response = xhr.responseText;
                                error.csrfRefreshFailed = true;
                                
                                if (onError) {
                                    onError(error, xhr);
                                }
                                reject(error);
                            }
                        };
                        
                        refreshXhr.onerror = () => {
                            const error = new Error('Session expired. Please refresh the page and try again.');
                            error.status = 419;
                            error.response = xhr.responseText;
                            error.csrfRefreshFailed = true;
                            
                            if (onError) {
                                onError(error, xhr);
                            }
                            reject(error);
                        };
                        
                        refreshXhr.send();
                    }, 0);
                    return; // Don't reject yet - waiting for retry
                }
                
                // Try to parse error response
                let errorMessage = `Network response was not ok: ${xhr.status}`;
                let errorData = null;
                
                try {
                    errorData = JSON.parse(xhr.responseText);
                    if (errorData.message) {
                        errorMessage = errorData.message;
                    } else if (errorData.errors) {
                        errorMessage = 'Validation error';
                    }
                } catch (e) {
                    // Use default error message
                }
                
                const error = new Error(errorMessage);
                error.status = xhr.status;
                error.response = xhr.responseText;
                error.responseData = errorData;
                
                // Check if response includes a new CSRF token (some endpoints return it)
                if (errorData?.csrf_token) {
                    // Update token in DOM
                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    if (csrfMeta) {
                        csrfMeta.content = errorData.csrf_token;
                    }
                    const csrfInputs = document.querySelectorAll('input[name="_token"]');
                    csrfInputs.forEach(input => {
                        input.value = errorData.csrf_token;
                    });
                }
                
                if (onError) {
                    onError(error, xhr);
                }
                reject(error);
            }
        };
        
        xhr.onerror = () => {
            const error = new Error('Network request failed');
            if (onError) {
                onError(error, xhr);
            }
            reject(error);
        };
        
        xhr.ontimeout = () => {
            const error = new Error('Request timeout');
            if (onError) {
                onError(error, xhr);
            }
            reject(error);
        };
        
        // Send request
        try {
            if (body instanceof FormData) {
                xhr.send(body);
            } else if (typeof body === 'object' && body !== null) {
                xhr.send(JSON.stringify(body));
            } else if (body !== null) {
                xhr.send(body);
            } else {
                xhr.send();
            }
        } catch (e) {
            const error = new Error(`Failed to send request: ${e.message}`);
            if (onError) {
                onError(error, xhr);
            }
            reject(error);
        }
    });
}

/**
 * Show a Bootstrap toast notification
 * Uses template cloning instead of createElement/innerHTML (follows best practices)
 * @param {string} message - The message to display
 * @param {string} type - Toast type: 'success', 'error', 'warning', 'info' (default: 'info')
 * @param {number} delay - Auto-hide delay in milliseconds (default: 5000)
 * @param {string} htmlContent - Optional HTML content to use instead of message (must be sanitized)
 * @returns {HTMLElement} The created toast element
 */
export function showToast(message, type = 'info', delay = 5000, htmlContent = null) {
    const toastContainer = getToastContainer();
    if (!toastContainer) return null;
    
    // Get toast template
    const toastTemplate = document.getElementById('toastTemplate');
    if (!toastTemplate) {
        // Fallback: if template doesn't exist, return null (should not happen in production)
        return null;
    }
    
    // Clone template
    const toast = toastTemplate.content.cloneNode(true).querySelector('.toast');
    if (!toast) return null;
    
    const toastId = `toast_${type}_${Date.now()}_${Math.random().toString(36).substring(2, 11)}`;
    toast.id = toastId;
    
    // Map type to Bootstrap background classes
    const bgClassMap = {
        'success': 'bg-success',
        'error': 'bg-danger',
        'danger': 'bg-danger',
        'warning': 'bg-warning',
        'info': 'bg-info'
    };
    
    const bgClass = bgClassMap[type] || 'bg-info';
    toast.classList.add(bgClass);
    
    // For warning toasts, use dark text and close button
    if (type === 'warning') {
        // Remove text-white class and add text-dark
        toast.classList.remove('text-white');
        toast.classList.add('text-dark');
        const closeButton = toast.querySelector('.btn-close');
        if (closeButton) {
            closeButton.classList.remove('btn-close-white');
            // btn-close class is already there, just ensure it's not white
        }
    }
    
    // Get toast body element
    const toastBody = toast.querySelector('.toast-body');
    if (!toastBody) return null;
    
    // Set content
    if (htmlContent === true) {
        // htmlContent is a boolean flag - use message as HTML
        toastBody.innerHTML = message;
    } else if (htmlContent) {
        // htmlContent is a string - use it as HTML content
        toastBody.innerHTML = htmlContent;
    } else {
        // Use textContent for safe text insertion (automatically escapes HTML)
        toastBody.textContent = message;
    }
    
    toastContainer.appendChild(toast);
    
    // Initialize and show toast
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const bsToast = new bootstrap.Toast(toast, {
            autohide: delay > 0,
            delay: delay
        });
        bsToast.show();
        
        // Remove toast element after it's hidden
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        }, { once: true });
    }
    
    return toast;
}

/**
 * Escape HTML to prevent XSS attacks
 * @param {string} text - Text to escape
 * @returns {string} Escaped HTML-safe string
 */
export function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Throttle function calls - ensures function is called at most once per time limit
 * Useful for scroll handlers, mouse move events, etc.
 * @param {Function} func - Function to throttle
 * @param {number} limit - Time limit in milliseconds
 * @returns {Function} Throttled function
 */
export function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => {
                inThrottle = false;
            }, limit);
        }
    };
}

/**
 * Get query parameter from current URL
 * @param {string} param - Parameter name
 * @returns {string|null} Parameter value or null if not found
 */
export function getQueryParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
}

/**
 * Format number with commas for readability (e.g., 1000 -> 1,000)
 * @param {number} num - Number to format
 * @returns {string} Formatted number string
 */
export function formatNumber(num) {
    if (num === null || num === undefined || isNaN(num)) return '0';
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Remove query parameter from URL without page reload
 * @param {string} param - Parameter name to remove
 */
export function removeQueryParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.delete(param);
    
    // Build new URL using URL constructor (PS4 supports it)
    const url = new URL(window.location.href);
    url.search = urlParams.toString();
    window.history.replaceState({}, document.title, url.toString());
}

/**
 * Get CSRF token from meta tag or hidden input
 * @returns {string|null} CSRF token or null if not found
 */
export function getCsrfToken() {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta && csrfMeta.content) {
        return csrfMeta.content;
    }
    const csrfInput = document.querySelector('input[name="_token"]');
    if (csrfInput && csrfInput.value) {
        return csrfInput.value;
    }
    return null;
}

/**
 * Refresh CSRF token from server
 * Fetches a new token and updates both meta tag and all form inputs
 * @returns {Promise<string|null>} Promise that resolves with the new token or null if failed
 */
export async function refreshCsrfToken() {
    try {
        // Use makeRequest without CSRF token (GET request, no token needed)
        const response = await makeRequest('/csrf-token', {
            method: 'GET',
            skipCsrf: true
        });
        
        const data = response.data || response;
        const newToken = data?.token || data?.data?.token;
        
        if (!newToken) {
            console.warn('CSRF token refresh: No token in response');
            return null;
        }
        
        // Update meta tag
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            csrfMeta.content = newToken;
        }
        
        // Update all form inputs with name="_token"
        const csrfInputs = document.querySelectorAll('input[name="_token"]');
        csrfInputs.forEach(input => {
            input.value = newToken;
        });
        
        return newToken;
    } catch (error) {
        console.error('CSRF token refresh failed:', error);
        return null;
    }
}

/**
 * Update CSRF token in DOM (meta tag and form inputs)
 * Used when server returns a new token in response
 * @param {string} newToken - The new CSRF token
 */
export function updateCsrfToken(newToken) {
    if (!newToken) return;
    
    // Update meta tag
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        csrfMeta.content = newToken;
    }
    
    // Update all form inputs with name="_token"
    const csrfInputs = document.querySelectorAll('input[name="_token"]');
    csrfInputs.forEach(input => {
        input.value = newToken;
    });
}

/**
 * Get data attribute value from script tag
 * @param {string} attributeName - Data attribute name (e.g., 'data-slug', 'data-channels')
 * @returns {string|null} Attribute value or null if not found
 */
export function getScriptData(attributeName) {
    const scriptTag = document.querySelector(`script[${attributeName}]`);
    if (scriptTag) {
        return scriptTag.getAttribute(attributeName);
    }
    return null;
}

/**
 * Parse JSON data from script tag attribute
 * @param {string} attributeName - Data attribute name
 * @param {*} defaultValue - Default value if parsing fails (default: null)
 * @returns {*} Parsed JSON data or default value
 */
export function getScriptDataJson(attributeName, defaultValue = null) {
    const data = getScriptData(attributeName);
    if (!data) return defaultValue;
    
    try {
        return JSON.parse(data);
    } catch (e) {
        return defaultValue;
    }
}

/**
 * Parse JSON data from element's dataset attribute
 * @param {HTMLElement} element - DOM element
 * @param {string} attributeName - Dataset attribute name (without 'data-' prefix, e.g., 'catGifSelector')
 * @param {*} defaultValue - Default value if parsing fails (default: null)
 * @returns {*} Parsed JSON data or default value
 */
export function getDatasetJson(element, attributeName, defaultValue = null) {
    if (!element || !element.dataset) return defaultValue;
    
    const data = element.dataset[attributeName];
    if (!data) return defaultValue;
    
    try {
        return JSON.parse(data);
    } catch (e) {
        return defaultValue;
    }
}

/**
 * Parse JSON data from element's textContent (useful for script tags)
 * @param {HTMLElement} element - DOM element (typically a script tag)
 * @param {*} defaultValue - Default value if parsing fails (default: null)
 * @returns {*} Parsed JSON data or default value
 */
export function getElementJson(element, defaultValue = null) {
    if (!element || !element.textContent) return defaultValue;
    
    try {
        return JSON.parse(element.textContent);
    } catch (e) {
        return defaultValue;
    }
}

/**
 * Show loading spinner by element ID
 * @param {string} elementId - Element ID (default: 'loadingSpinner')
 */
export function showLoadingSpinner(elementId = 'loadingSpinner') {
    const spinner = document.getElementById(elementId);
    if (spinner) {
        spinner.classList.remove('d-none');
        spinner.classList.add('d-flex');
        spinner.style.setProperty('display', 'flex', 'important');
    }
}

/**
 * Hide loading spinner by element ID
 * @param {string} elementId - Element ID (default: 'loadingSpinner')
 */
export function hideLoadingSpinner(elementId = 'loadingSpinner') {
    const spinner = document.getElementById(elementId);
    if (spinner) {
        spinner.classList.remove('d-flex');
        spinner.classList.add('d-none');
        spinner.style.setProperty('display', 'none', 'important');
    }
}

/**
 * Build query string from parameters object
 * Filters out null/undefined/empty 'all' values
 * @param {object} params - Parameters object
 * @returns {string} Query string (without leading ?)
 */
export function buildQueryString(params) {
    const urlParams = new URLSearchParams();
    
    Object.keys(params).forEach(key => {
        const value = params[key];
        // Skip null, undefined, empty strings, or 'all' values
        if (value !== null && value !== undefined && value !== '' && value !== 'all') {
            urlParams.set(key, value);
        }
    });
    
    return urlParams.toString();
}

/**
 * Debounce function - delays execution until after wait time has passed
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @param {boolean} immediate - Whether to execute immediately on first call
 * @returns {Function} Debounced function
 */
export function debounce(func, wait, immediate = false) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            timeout = null;
            if (!immediate) func.apply(this, args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(this, args);
    };
}

/**
 * Get translated message with fallback
 * @param {string} key - Translation key (e.g., 'messages.unauthorized_access')
 * @param {string} fallback - Fallback message if translation not found
 * @returns {string} Translated message or fallback
 */
export function getTranslation(key, fallback) {
    const translations = window.appTranslations || {};
    const parts = key.split('.');
    let value = translations;
    
    for (const part of parts) {
        if (value && typeof value === 'object' && part in value) {
            value = value[part];
        } else {
            return fallback;
        }
    }
    
    return (value && typeof value === 'string' && value !== key) ? value : fallback;
}

/**
 * Parse integer with validation
 * @param {*} value - Value to parse
 * @param {number|null} defaultValue - Default value if invalid (default: null)
 * @returns {number|null} Parsed integer or default value
 */
export function parseIntSafe(value, defaultValue = null) {
    if (value === null || value === undefined || value === '') {
        return defaultValue;
    }
    
    const parsed = parseInt(value, 10);
    return isNaN(parsed) ? defaultValue : parsed;
}

/**
 * Update element text content safely
 * @param {string} elementId - Element ID
 * @param {string} text - Text content
 */
export function updateElementText(elementId, text) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = text;
    }
}

/**
 * Toggle visibility classes on element
 * @param {string} elementId - Element ID
 * @param {boolean} show - Whether to show (true) or hide (false)
 * @param {string} showClass - Class to add when showing (default: 'd-block')
 * @param {string} hideClass - Class to add when hiding (default: 'd-none')
 */
export function toggleVisibility(elementId, show, showClass = 'd-block', hideClass = 'd-none') {
    const element = document.getElementById(elementId);
    if (element) {
        if (show) {
            element.classList.remove(hideClass);
            element.classList.add(showClass);
        } else {
            element.classList.remove(showClass);
            element.classList.add(hideClass);
        }
    }
}
