/**
 * Device API Utilities
 * Shared functions for device-related API calls
 */

import { makeRequest } from './utils.js';

/**
 * Fetches registered users for a durable device_uid
 * Uses XMLHttpRequest for universal browser compatibility
 * @param {string} deviceUid - The durable device UUID
 * @param {string} registeredUsersRoute - The API route for fetching registered users
 * @param {string} csrfToken - CSRF token for the request
 * @returns {Promise<Array>} Promise that resolves to array of user objects
 * @throws {Error} If the request fails or route/token is missing
 */
export function fetchRegisteredUsers(deviceUid, registeredUsersRoute, csrfToken) {
    if (!registeredUsersRoute || !csrfToken) {
        return Promise.reject(new Error('Missing registered users route or CSRF token'));
    }
    
    return makeRequest(registeredUsersRoute, {
        method: 'POST',
        body: {
            device_uid: deviceUid || null
        },
        headers: {
            'X-CSRF-TOKEN': csrfToken
        },
        responseType: 'json'
    })
    .then(response => {
        // Extract data from response object
        // Handle both new format { data, headers, xhr } and old format (direct array)
        let users = null;
        
        // Handle case where response might be a string (shouldn't happen with responseType: 'json', but defensive)
        if (typeof response === 'string') {
            try {
                response = JSON.parse(response);
            } catch (e) {
                console.error('fetchRegisteredUsers: Failed to parse string response:', e);
                return [];
            }
        }
        
        // Check if response is the new format (has data property)
        // The API returns a direct array, but makeRequest wraps it in { data, headers, xhr }
        if (response && typeof response === 'object') {
            // Check for new format first (has 'data' property)
            if ('data' in response) {
                users = response.data;
            } else if (Array.isArray(response)) {
                // Response is directly the array (shouldn't happen with new makeRequest, but defensive)
                users = response;
            } else {
                // Response is an object but not array and no 'data' property
                // Try to find users array in common property names
                users = response.users || response.data || response;
            }
        } else if (Array.isArray(response)) {
            // Old format: response is directly the array
            users = response;
        } else {
            // Fallback: try response as-is
            users = response;
        }
        
        // Ensure we always return an array
        const result = Array.isArray(users) ? users : [];
        
        // Log response details for troubleshooting if needed
        if (!Array.isArray(users) || result.length === 0) {
            console.log('fetchRegisteredUsers: Response details', {
                responseType: typeof response,
                isArray: Array.isArray(response),
                hasData: response && typeof response === 'object' && 'data' in response,
                responseKeys: response && typeof response === 'object' ? Object.keys(response) : null,
                usersType: typeof users,
                isUsersArray: Array.isArray(users),
                usersLength: Array.isArray(users) ? users.length : 'N/A',
                resultLength: result.length
            });
        }
        
        return result;
    })
    .catch(error => {
        console.error('fetchRegisteredUsers error:', error);
        throw error;
    });
}

/**
 * Refreshes device capabilities on the server
 * @param {object} capabilities - Capability object to send
 * @param {string} route - The API route for refreshing capabilities
 * @param {string} csrfToken - CSRF token for the request
 * @returns {Promise} Promise that resolves when capabilities are refreshed
 */
export function refreshCapabilities(capabilities, route, csrfToken) {
    if (!route) {
        return Promise.reject(new Error('Missing capability refresh route'));
    }

    const headers = {};
    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    return makeRequest(route, {
        method: 'POST',
        body: {
            capabilities: capabilities
        },
        headers: headers,
        responseType: 'json'
    }).then(response => {
        // Extract data from response object
        return response.data || response; // Backward compatibility fallback
    });
}
