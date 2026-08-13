/**
 * Welcome page JavaScript
 * Handles user selection and password login modal
 */

import { collectBrowserData, collectCapabilities, getOrCreateDeviceUid, persistDeviceUid, setDeviceUidInForms } from '../../core/device-identity.js';
import { fetchRegisteredUsers } from '../../core/device-api.js';
import { openModal, setupModalFocus } from '../../core/modal-utils.js';
import { makeRequest, getElementJson, getCsrfToken, toggleVisibility, getTranslation } from '../../core/utils.js';
import { DeviceConstants, SessionConstants, TimingConstants } from '../../core/constants.js';
import { errorHandler } from '../../core/error-handler.js';
import { initI18n, t } from '../../core/i18n.js';

// Module state
let deviceUid = null;
let browserData = null;
let capabilitySnapshot = null;
let registeredUsers = [];
let availableCatGifs = [];
let registeredUsersRoute = null;
let catGifBasePath = null;
let moduleCsrfToken = null; // CSRF token stored in module scope, not global
let userSelectionDataMap = null; // User data map for event delegation (module scope)

/**
 * Ensure device UID is available and synced into forms.
 */
function ensureDeviceUidInForms() {
    browserData = refreshBrowserSnapshot(true);
    deviceUid = getOrCreateDeviceUid?.() ?? deviceUid;
    if (deviceUid && setDeviceUidInForms) {
        setDeviceUidInForms(deviceUid, browserData, capabilitySnapshot);
    }
    return deviceUid;
}

/**
 * Persist device_uid from a successful API response when present.
 * @param {object} responseData
 */
function applyDeviceUidFromResponse(responseData) {
    if (responseData?.device_uid && persistDeviceUid) {
        deviceUid = persistDeviceUid(responseData.device_uid) || responseData.device_uid;
    }
}

// Initialize configuration from embedded JSON script tag
function initializeConfig() {
    // Read JSON configuration from the script tag's text content
    const configElement = document.querySelector('script[data-welcome-config]');
    if (!configElement) {
        // Config element not found - use fallbacks
        moduleCsrfToken = getCsrfToken?.() || '';
        return;
    }
    
    const config = getElementJson?.(configElement, {}) || {};
    if (config && Object.keys(config).length > 0) {
        availableCatGifs = config.catGifs || [];
        registeredUsersRoute = config.registeredUsersRoute || null;
        catGifBasePath = config.catGifBasePath || '';
        
        // Get CSRF token - store in module scope, not global
        moduleCsrfToken = config.csrfToken || getCsrfToken?.() || '';
            
        // Store validation error state in body dataset
        if (config.hasPasswordError === true || config.hasPasswordError === 'true') {
            document.body.dataset.hasPasswordError = 'true';
        }
        if (config.oldUsername) {
            document.body.dataset.oldUsername = config.oldUsername;
        }
        if (config.oldDeviceName) {
            document.body.dataset.oldDeviceName = config.oldDeviceName;
        }
            
        // Store duplicate device error in body dataset instead of window
        if (config.duplicateDeviceError) {
            document.body.dataset.duplicateDeviceError = JSON.stringify(config.duplicateDeviceError);
        }
    } else {
        // Config parsed but empty - use fallbacks
        moduleCsrfToken = getCsrfToken?.() || '';
    }
}

function refreshBrowserSnapshot(forceUpdate) {
    if (!collectBrowserData) {
        browserData = browserData || {};
        if (!capabilitySnapshot) {
            capabilitySnapshot = null;
        }
        return browserData;
    }

    if (forceUpdate || !browserData) {
        browserData = collectBrowserData();
    }

    if (collectCapabilities && browserData) {
        capabilitySnapshot = collectCapabilities(browserData);
    } else if (forceUpdate) {
        capabilitySnapshot = null;
    }

    return browserData;
}

// Get random cat GIF
function getRandomCatGif() {
    if (availableCatGifs.length === 0) return '';
    const randomIndex = Math.floor(Math.random() * availableCatGifs.length);
    return availableCatGifs[randomIndex];
}

/**
 * Displays the user selection grid with registered users
 * Uses template cloning instead of createElement/innerHTML (follows best practices)
 * @param {Array} users - Array of user objects with username, device_name, profile_picture
 */
function showUserSelection(users) {
    // Always hide loading view first, even if templates are missing
    toggleVisibility?.('loadingView', false, 'd-block', 'd-none');
    
    const grid = document.getElementById('userSelectionGrid');
    if (!grid) {
        toggleVisibility?.('userSelectionView', true, 'd-block', 'd-none');
        return;
    }
    
    // Get templates
    const userTileTemplate = document.getElementById('userTileTemplate');
    const otherOptionTemplate = document.getElementById('otherOptionTemplate');
    if (!userTileTemplate || !otherOptionTemplate) {
        toggleVisibility?.('userSelectionView', true, 'd-block', 'd-none');
        return;
    }
    
    // Show user selection view
    toggleVisibility?.('userSelectionView', true, 'd-block', 'd-none');
    
    // Clear grid
    grid.innerHTML = '';
    
    // Add each user using template cloning
    for (const user of users) {
        // Clone template
        const userTile = userTileTemplate.content.cloneNode(true);
        const tileDiv = userTile.querySelector('.col-auto');
        const tile = userTile.querySelector('.user-avatar-tile');
        const img = userTile.querySelector('img');
        const usernameH5 = userTile.querySelector('h5');
        const deviceNameSmall = userTile.querySelector('small');
        
        // Determine profile picture
        let profilePictureFilename = '';
        let profileImgSrc = '';
        
        if (user.profile_picture) {
            profilePictureFilename = user.profile_picture;
            profileImgSrc = `${catGifBasePath}${encodeURIComponent(user.profile_picture)}`;
        } else {
            const randomGif = getRandomCatGif();
            if (randomGif) {
                profilePictureFilename = randomGif;
                profileImgSrc = `${catGifBasePath}${encodeURIComponent(randomGif)}`;
            }
        }
        
        // Set image source
        if (img && profileImgSrc) {
            img.src = profileImgSrc;
        }
        
        // Set username and device name
        const deviceName = user.device_name || DeviceConstants?.DEFAULT_DEVICE_NAME;
        if (usernameH5) {
            usernameH5.textContent = user.username;
        }
        if (deviceNameSmall) {
            deviceNameSmall.textContent = deviceName;
        }
        
        // Set data attributes for Bootstrap modal and user data
        if (tile) {
            tile.setAttribute('data-user-email', user.email || '');
            tile.setAttribute('data-user-username', user.username || '');
            tile.setAttribute('data-user-device-name', deviceName);
            tile.setAttribute('data-user-profile-picture', profilePictureFilename);
        }
        
        // Append to grid
        grid.appendChild(userTile);
    }
    
    // Add "Other" option using template cloning
    const otherOption = otherOptionTemplate.content.cloneNode(true);
    const otherLink = otherOption.querySelector('a');
    
    // Update href — do not put device_uid in the query string (fixation risk).
    // localStorage / sessionStorage already carry the uid into the register page.
    if (otherLink) {
        otherLink.href = '/register-device';
    }
    
    grid.appendChild(otherOption);
}

/**
 * Setup Bootstrap modal event handler to populate modal data when it opens
 * Uses Bootstrap's native show.bs.modal event - much simpler than custom click handlers
 */
function setupModalDataHandler() {
    const modal = document.getElementById('passwordLoginModal');
    if (!modal) {
        console.warn('Password login modal not found');
        return;
    }
    
    // Ensure Bootstrap is available
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        console.warn('Bootstrap not available, retrying in 100ms');
        setTimeout(setupModalDataHandler, 100);
        return;
    }
    
    // Remove existing listener to avoid duplicates
    modal.removeEventListener('show.bs.modal', handleModalShow);
    
    // Use Bootstrap's native show.bs.modal event to populate modal data
    modal.addEventListener('show.bs.modal', handleModalShow);
}

/**
 * Handles Bootstrap modal show event - populates modal with user data from clicked tile
 * @param {Event} e - Bootstrap modal show event
 */
function handleModalShow(e) {
    // Get the button/tile that triggered the modal
    const trigger = e.relatedTarget;
    if (!trigger) return;
    
    // Check if it's a user tile (has data-user-email attribute)
    const email = trigger.getAttribute('data-user-email');
    const username = trigger.getAttribute('data-user-username');
    const deviceName = trigger.getAttribute('data-user-device-name');
    const profilePicture = trigger.getAttribute('data-user-profile-picture');
    
    if (!email || !username) {
        // Not a valid user tile - don't populate modal
        return;
    }
    
    // Hide back button when opening modal
    const welcomeBackBtn = document.getElementById('welcomeBackBtn');
    if (welcomeBackBtn) {
        welcomeBackBtn.classList.add('d-none');
        welcomeBackBtn.classList.remove('d-block');
    }
    
    // Populate modal with user data
    openPasswordLoginModal(email, username, deviceName, profilePicture);
}

// "Other" option is now a simple link - no JavaScript handler needed
// This is the simplest and most reliable approach

/**
 * Stores password form state in sessionStorage before navigating to forgot password page
 */
function storePasswordFormState() {
    // Get from modal
    const emailInput = document.getElementById('passwordLoginModalEmail');
    const email = emailInput?.value || '';
    const profilePictureContainer = document.getElementById('passwordLoginModalProfilePicture');
    const profileImg = profilePictureContainer?.querySelector('img');
    const profilePictureSrc = profileImg?.src || '';
    
    // Get actual device name from display title (format: "username (device name)")
    // The hidden input contains the password-only login flag which is not the display name
    const usernameDisplay = document.getElementById('passwordLoginModalUsernameDisplay');
    let deviceName = DeviceConstants?.DEFAULT_DEVICE_NAME;
    if (usernameDisplay?.textContent) {
        const titleText = usernameDisplay.textContent;
        // Extract device name from parentheses: "username (device name)"
        const match = titleText.match(/\(([^)]+)\)/);
        if (match?.[1]) {
            deviceName = match[1].trim();
        }
    }
    
    if (email) {
        // Extract just the filename from the full path
        let profilePictureFilename = '';
        if (profilePictureSrc) {
            const urlParts = profilePictureSrc.split('/');
            profilePictureFilename = urlParts[urlParts.length - 1];
            try {
                profilePictureFilename = decodeURIComponent(profilePictureFilename);
            } catch (e) {
                // If decoding fails, use as-is
            }
        }
        
        if (SessionConstants) {
            sessionStorage.setItem(SessionConstants.PASSWORD_FORM_EMAIL, email);
            sessionStorage.setItem(SessionConstants.PASSWORD_FORM_DEVICE_NAME, deviceName);
            if (profilePictureFilename) {
                sessionStorage.setItem(SessionConstants.PASSWORD_FORM_PROFILE_PICTURE, profilePictureFilename);
            }
        }
    }
}

/**
 * Opens the password login modal with user data
 * @param {string} email - The email to submit (for authentication)
 * @param {string} username - The username to display
 * @param {string} deviceName - The device name to display and submit
 * @param {string} profilePictureFilename - The profile picture filename to display
 */
function openPasswordLoginModal(email, username, deviceName, profilePictureFilename) {
    // Set form values in modal
    const modalEmail = document.getElementById('passwordLoginModalEmail');
    const modalDeviceName = document.getElementById('passwordLoginModalDeviceName');
    const modalUsernameDisplay = document.getElementById('passwordLoginModalUsernameDisplay');
    
    if (modalEmail) modalEmail.value = email;
    // IMPORTANT: Always set device_name to the password-only login flag for password-only login
    // The actual device name is only used for display purposes
    if (modalDeviceName && DeviceConstants) {
        modalDeviceName.value = DeviceConstants.PASSWORD_ONLY_LOGIN_FLAG;
    }
    
    // Update title to show: username (device name) - use actual device name for display only
    const titleText = `${username} (${deviceName || DeviceConstants?.DEFAULT_DEVICE_NAME || ''})`;
    if (modalUsernameDisplay) modalUsernameDisplay.textContent = titleText;
    
    // Store profile picture data to set after modal is shown
    // Note: catGifBasePath is read fresh in the callback to ensure it's initialized
    const profilePictureData = {
        filename: profilePictureFilename
    };
    
    // Ensure device_uid is set in modal
    if (deviceUid) {
        const modalDeviceUid = document.getElementById('passwordLoginModalDeviceUid');
        if (modalDeviceUid) modalDeviceUid.value = deviceUid;
    }
    
    // Open modal using shared utility
    const modalElement = document.getElementById('passwordLoginModal');
    if (modalElement && openModal) {
        // Hide back button when modal opens
        const welcomeBackBtn = document.getElementById('welcomeBackBtn');
        if (welcomeBackBtn) {
            welcomeBackBtn.classList.add('d-none');
            welcomeBackBtn.classList.remove('d-block');
        }
        
        // Open modal with custom callback for profile picture and field clearing
        openModal('passwordLoginModal', () => {
            // Set profile picture
            // Use setTimeout to ensure DOM is fully updated after modal is shown
            setTimeout(() => {
                // Try to find the container - first by ID, then search within modal
                let modalProfilePicture = document.getElementById('passwordLoginModalProfilePicture');
                if (!modalProfilePicture) {
                    // Fallback: search within the modal element
                    const modalEl = document.getElementById('passwordLoginModal');
                    if (modalEl) {
                        modalProfilePicture = modalEl.querySelector('#passwordLoginModalProfilePicture');
                    }
                }
                
                if (!modalProfilePicture) {
                    return;
                }
            
                // Find the img element within the container (should exist from Blade template)
                // Try multiple selectors to ensure we find the img element
                let img = modalProfilePicture.querySelector('img.user-avatar-image');
                if (!img) {
                    // Fallback: try finding any img element in the container
                    img = modalProfilePicture.querySelector('img');
                }
                const circleDiv = modalProfilePicture.querySelector('.user-avatar-circle');
                
                if (img && circleDiv) {
                    // Determine which image to use
                    let imageSrc = null;
                    
                    // Read catGifBasePath fresh from module scope (may not be initialized when function was called)
                    const currentCatGifBasePath = catGifBasePath || '';
                    
                    if (profilePictureData.filename && currentCatGifBasePath) {
                        // Use provided profile picture
                        const encodedFilename = encodeURIComponent(profilePictureData.filename);
                        imageSrc = `${currentCatGifBasePath}${encodedFilename}`;
                    } else if (!profilePictureData.filename && currentCatGifBasePath) {
                        // No profile picture provided - try to get a random one
                        const randomGif = getRandomCatGif();
                        if (randomGif) {
                            const encodedFilename = encodeURIComponent(randomGif);
                            imageSrc = `${currentCatGifBasePath}${encodedFilename}`;
                        }
                    }
                    
                    // Set the image source if we have one
                    if (imageSrc) {
                        img.src = imageSrc;
                        img.alt = username || getTranslation?.('common.profile', 'Profile') || 'Profile';
                        // Show the image using Bootstrap classes
                        img.classList.remove('d-none');
                        // Ensure circle is visible
                        circleDiv.classList.remove('d-none');
                    } else {
                        // No image available - ensure circle is visible but hide img
                        img.classList.add('d-none');
                        circleDiv.classList.remove('d-none');
                    }
                } else if (circleDiv) {
                    // Circle exists but img not found - ensure circle is visible
                    circleDiv.classList.remove('d-none');
                }
            }, TimingConstants?.MODAL_PROFILE_PICTURE_DELAY || 200);
            
            // Clear password field if no validation errors (focus handled by setupModalFocus)
            const passwordField = document.getElementById('passwordLoginModalPassword');
            if (passwordField) {
                // Only clear value if there are no validation errors
                const hasErrors = passwordField.classList.contains('is-invalid');
                if (!hasErrors) {
                    passwordField.value = ''; // Clear any previous value
                }
            }
        });
    
        // Show back button and user selection when modal is hidden (closed)
        modalElement.addEventListener('hidden.bs.modal', () => {
            const welcomeBackBtn = document.getElementById('welcomeBackBtn');
            if (welcomeBackBtn) {
                welcomeBackBtn.classList.add('d-none');
                welcomeBackBtn.classList.remove('d-block');
            }
            
            // If we have registered users, show them; otherwise check for them
            if (registeredUsers && registeredUsers.length > 0) {
                // Hide loading spinner and show user selection
                toggleVisibility?.('loadingView', false, 'd-block', 'd-none');
                showUserSelection(registeredUsers);
            } else if (registeredUsersRoute && moduleCsrfToken) {
                // No users cached yet - fetch them
                checkRegisteredUsers();
            }
        }, { once: false });
    }
}

/**
 * Opens the password login modal from a toast notification link
 * @param {string} email - The user's email (for authentication)
 * @param {string} username - The username (for display)
 * @param {string} deviceName - The device name
 * @param {string} profilePictureFilename - Optional profile picture filename
 */
function openLoginModalFromToast(email, username, deviceName, profilePictureFilename) {
    // If email is provided, use it directly; otherwise try to find user by username
    let user = null;
    let profilePicture = '';
    let finalEmail = email || '';
    let finalUsername = username || '';
    
    if (registeredUsers && registeredUsers.length > 0) {
        if (email) {
            // Find by email (preferred)
            user = registeredUsers.find(u => u.email === email);
        } else if (username) {
            // Fallback: find by username
            user = registeredUsers.find(u => u.username === username);
        }
    }
    
    if (user) {
        if (user.profile_picture) {
            profilePicture = user.profile_picture;
        }
        // Use email and username from user object if found
        finalEmail = user.email || finalEmail;
        finalUsername = user.username || finalUsername;
    }
    
    // Use provided profile picture if available, otherwise use found one
    if (profilePictureFilename) {
        profilePicture = profilePictureFilename;
    }
    
    // Ensure we have email before proceeding
    if (finalEmail) {
        // Ensure we have device UID and browser data
        if (!deviceUid || !browserData) {
            ensureDeviceUidInForms();
        }
        openPasswordLoginModal(finalEmail, finalUsername, deviceName, profilePicture);
    }
}

/**
 * Extract users array from fetchRegisteredUsers response
 * @param {*} response
 * @returns {Array}
 */
function extractUsersFromResponse(response) {
    let users = response;
    if (response && typeof response === 'object' && 'data' in response && Array.isArray(response.data)) {
        users = response.data;
    } else if (response && typeof response === 'object' && !Array.isArray(response) && 'data' in response) {
        users = response.data || [];
    } else if (response && typeof response === 'object' && 'error' in response) {
        console.warn('API returned error:', response.error);
        users = [];
    }
    return Array.isArray(users) ? users : [];
}

// Check for registered users on page load
function checkRegisteredUsers() {
    // Track if timeout has fired and if user selection has been shown
    // This prevents race conditions where timeout shows empty state but users load later
    let timeoutFired = false;
    let userSelectionShown = false;
    
    // Add timeout fallback - show user selection after 10 seconds max
    // This prevents indefinite loading on older browsers where
    // crypto.subtle or network requests may hang
    const timeoutId = setTimeout(() => {
        timeoutFired = true;
        const loadingView = document.getElementById('loadingView');
        if (loadingView && !loadingView.classList.contains('d-none') && !userSelectionShown) {
            // Timeout reached - show user selection with empty array as fallback
            // But keep promise chain alive to update with real data if it arrives later
            toggleVisibility?.('loadingView', false, 'd-block', 'd-none');
            toggleVisibility?.('userSelectionView', true, 'd-block', 'd-none');
            showUserSelection([]); // Show "Other" option as fallback
            userSelectionShown = true;
        }
    }, TimingConstants?.WELCOME_USER_LOADING_TIMEOUT || 10000);
    
    // Check if we have validation errors and should show password modal
    const hasPasswordError = document.body.dataset.hasPasswordError === 'true';
    const oldUsername = document.body.dataset.oldUsername || null;
    const oldDeviceName = document.body.dataset.oldDeviceName || null;
    
    // Check if there's a password error and old username
    if (hasPasswordError && oldUsername) {
        // Clear timeout since we're handling password error flow
        clearTimeout(timeoutId);
        
        // We have a validation error, show password modal with error
        // Hide loading view immediately
        toggleVisibility?.('loadingView', false, 'd-block', 'd-none');
        
        ensureDeviceUidInForms();
        
        // Check for registered users to get profile picture
        fetchRegisteredUsers?.(deviceUid, registeredUsersRoute, moduleCsrfToken)
            .then((response) => {
                registeredUsers = extractUsersFromResponse(response);
                
                // Find user's profile picture
                let user = null;
                let profilePicture = '';
                if (registeredUsers && registeredUsers.length > 0) {
                    user = registeredUsers.find(u => u.username === oldUsername);
                }
                if (user) {
                    if (user.profile_picture) {
                        profilePicture = user.profile_picture;
                    }
                    // Get email from user object (required for authentication)
                    const email = user.email || '';
                    
                    // Open modal with error state
                    openPasswordLoginModal(email, oldUsername, oldDeviceName, profilePicture);
                }
                
                // Mark password field as invalid after modal is shown
                const modalElement = document.getElementById('passwordLoginModal');
                if (modalElement) {
                    modalElement.addEventListener('shown.bs.modal', () => {
                        const passwordField = document.getElementById('passwordLoginModalPassword');
                        if (passwordField) {
                            passwordField.classList.add('is-invalid');
                            passwordField.focus();
                        }
                    }, { once: true });
                }
            })
            .catch(() => {
                // Error fetching users, still open modal (don't redirect!)
                // Note: Without email, authentication will fail, but we show the modal anyway
                registeredUsers = [];
                openPasswordLoginModal('', oldUsername, oldDeviceName, '');
                
                // Mark password field as invalid after modal is shown
                const modalElement = document.getElementById('passwordLoginModal');
                if (modalElement) {
                    modalElement.addEventListener('shown.bs.modal', () => {
                        const passwordField = document.getElementById('passwordLoginModalPassword');
                        if (passwordField) {
                            passwordField.classList.add('is-invalid');
                            passwordField.focus();
                        }
                    }, { once: true });
                }
            });
        
        return; // Don't continue with normal flow
    }
    
    // Normal flow - check for registered users
    // Validate configuration before proceeding
    if (!registeredUsersRoute || !moduleCsrfToken) {
        // Missing critical configuration - show user selection with "Other" option
        clearTimeout(timeoutId);
        toggleVisibility?.('loadingView', false, 'd-block', 'd-none');
        showUserSelection([]); // Show "Other" option as fallback
        userSelectionShown = true;
        return;
    }
    
    ensureDeviceUidInForms();
    
    // Check for registered users
    fetchRegisteredUsers?.(deviceUid, registeredUsersRoute, moduleCsrfToken)
        .then((response) => {
            const users = extractUsersFromResponse(response);
            
            // Clear timeout since we got the data
            clearTimeout(timeoutId);
            
            registeredUsers = users;
            toggleVisibility?.('loadingView', false, 'd-block', 'd-none');
            
            // Always show user selection - update UI even if timeout already fired
            // This handles the case where timeout showed empty state, but now we have real data
            if (timeoutFired && users && users.length > 0) {
                // Timeout already fired, but we got users - update the UI with real data
                showUserSelection(users);
            } else {
                // Normal case - show user selection
                showUserSelection(users);
            }
            userSelectionShown = true;
        })
        .catch((error) => {
            // Log error details
            console.error('checkRegisteredUsers: Error fetching registered users:', error);
            
            // Clear timeout on error
            clearTimeout(timeoutId);
            
            // Error fetching users - show user selection with empty array (will show "Other" option)
            toggleVisibility?.('loadingView', false, 'd-block', 'd-none');
            
            // Only show empty state if timeout hasn't already shown it
            if (!timeoutFired) {
                showUserSelection([]);
                userSelectionShown = true;
            }
            // If timeout already fired, UI is already shown - don't update again
        });
}

/**
 * Show toast notification for duplicate device error
 * Uses template cloning instead of createElement/innerHTML (follows best practices)
 * @param {string} message - The error message
 * @param {string} email - The user's email (for authentication)
 * @param {string} username - The username (for display)
 * @param {string} deviceName - The device name
 */
function showDuplicateDeviceToast(message, email, username, deviceName) {
    // Get toast container (should exist in Blade template)
    const toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) return;
    
    // Get toast template
    const toastTemplate = document.getElementById('toastTemplate');
    if (!toastTemplate) return;
    
    // Clone template
    const toast = toastTemplate.content.cloneNode(true).querySelector('.toast');
    if (!toast) return;
    
    const toastId = `duplicateDeviceToast_${Date.now()}`;
    toast.id = toastId;
    toast.classList.add('bg-danger');
    
    // Get toast body element
    const toastBody = toast.querySelector('.toast-body');
    if (!toastBody) return;
    
    // Create message text node (safe - no innerHTML)
    const messageText = document.createTextNode(message);
    toastBody.appendChild(messageText);
    
    // Create line break
    const br = document.createElement('br');
    toastBody.appendChild(br);
    
    // Create login link using createElement (no innerHTML)
    const loginLink = document.createElement('a');
    loginLink.href = '#';
    loginLink.className = 'toast-login-link text-white text-decoration-underline fw-bold';
    loginLink.setAttribute('data-email', email || '');
    loginLink.setAttribute('data-username', username || '');
    loginLink.setAttribute('data-device-name', deviceName || DeviceConstants?.DEFAULT_DEVICE_NAME || '');
    loginLink.textContent = t?.('auth.log_in_as', { username: username || email || 'user' }) || 'Log in';
    
    // Add click handler
    loginLink.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const linkEmail = loginLink.getAttribute('data-email');
        const linkUsername = loginLink.getAttribute('data-username');
        const linkDeviceName = loginLink.getAttribute('data-device-name');
        // Use setTimeout to ensure event handling works
        setTimeout(() => {
            // If we have email, use it directly; otherwise try to look up from registeredUsers
            if (linkEmail) {
                // Find user in registeredUsers to get full info
                const user = registeredUsers?.find(u => u.email === linkEmail);
                if (user) {
                    // Pass profile picture to modal
                    openLoginModalFromToast(user.email, user.username, linkDeviceName, user.profile_picture || '');
                } else {
                    // Fallback: use email as username for display
                    openLoginModalFromToast(linkEmail, linkUsername || linkEmail, linkDeviceName, '');
                }
            } else if (linkUsername) {
                // Fallback to old behavior (lookup by username)
                openLoginModalFromToast(linkUsername, linkDeviceName);
            }
        }, 0);
    }, false);
    
    toastBody.appendChild(loginLink);
    
    toastContainer.appendChild(toast);
    
    // Initialize and show toast
    if (typeof window !== 'undefined' && window.bootstrap?.Toast) {
        const bsToast = new window.bootstrap.Toast(toast, {
            autohide: true,
            delay: 10000
        });
        bsToast.show();
        
        // Remove toast element after it's hidden
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
}

/**
 * Fetch registered users and show selection (used by duplicate-device error flows)
 */
function fetchAndShowRegisteredUsers() {
    ensureDeviceUidInForms();
    fetchRegisteredUsers?.(deviceUid, registeredUsersRoute, moduleCsrfToken)
        .then((response) => {
            registeredUsers = extractUsersFromResponse(response);
            toggleVisibility?.('loadingView', false, 'd-block', 'd-none');
            showUserSelection(registeredUsers);
        })
        .catch(() => {
            toggleVisibility?.('loadingView', false, 'd-block', 'd-none');
            showUserSelection(registeredUsers || []);
        });
}

// Handle form submissions
function setupFormHandlers() {
    // Password login modal form - handle with AJAX to keep modal open on errors
    const passwordLoginModalForm = document.getElementById('passwordLoginModalForm');
    if (passwordLoginModalForm && makeRequest) {
        passwordLoginModalForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            
            // Ensure device_uid is set
            if (deviceUid) {
                const deviceUidInput = document.getElementById('passwordLoginModalDeviceUid');
                if (deviceUidInput) deviceUidInput.value = deviceUid;
            }
            
            // Ensure device_name is set to the password-only login flag for password-only login
            // This is critical - the controller uses this to detect password-only login
            const deviceNameInput = document.getElementById('passwordLoginModalDeviceName');
            if (deviceNameInput && DeviceConstants) {
                deviceNameInput.value = DeviceConstants.PASSWORD_ONLY_LOGIN_FLAG;
            }
            
            // Get form data
            const formData = new FormData(form);
            const passwordField = document.getElementById('passwordLoginModalPassword');
            const errorMessage = document.getElementById('passwordLoginModalError');
            
            // Clear previous errors
            if (passwordField) {
                passwordField.classList.remove('is-invalid');
            }
            if (errorMessage) {
                errorMessage.classList.add('d-none');
                errorMessage.textContent = '';
            }
            
            // Disable submit button during request
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton?.textContent || '';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = t?.('auth.logging_in') || 'Logging in...';
            }
            
            // Submit via AJAX using makeRequest utility
            const resetButton = () => {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText;
                }
            };
            
            const showError = (message) => {
                if (passwordField) {
                    passwordField.classList.add('is-invalid');
                    passwordField.focus();
                }
                if (errorMessage) {
                    // Handle Error objects - extract message property
                    let errorText = message;
                    if (message && typeof message === 'object') {
                        // If it's an Error object, get the message
                        if (message.message) {
                            errorText = message.message;
                        } else if (message.toString) {
                            errorText = message.toString();
                        } else {
                            errorText = null;
                        }
                    }
                    errorMessage.textContent = errorText || (t?.('common.error_occurred', 'An error occurred') || 'An error occurred');
                    errorMessage.classList.remove('d-none');
                }
            };
            
            try {
                const response = await makeRequest(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': moduleCsrfToken || getCsrfToken?.() || (() => {
                            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                            return csrfMeta?.content || '';
                        })(),
                        'Accept': 'application/json'
                    },
                    responseType: 'json'
                });
                
                resetButton();
                
                // Handle response object format from makeRequest
                // The response might be the response object {data, headers, xhr}, extract actual data if needed
                let responseData = response;
                if (response && typeof response === 'object' && 'data' in response && !('errors' in response) && !('success' in response) && !('redirect' in response)) {
                    // This is the response object {data, headers, xhr}, extract the actual data
                    responseData = response.data;
                }
                
                // Persist durable device UID from successful responses
                applyDeviceUidFromResponse(responseData);
                
                // Check content type to handle redirects
                const contentType = response.xhr?.getResponseHeader?.('content-type') || '';
                
                if (contentType.includes('application/json')) {
                    // If redirect URL is in response, follow it
                    if (responseData && (responseData.redirect || responseData.url)) {
                        window.location.href = responseData.redirect || responseData.url;
                        return;
                    }
                    // If success is true but no redirect, redirect to home
                    if (responseData?.success) {
                        window.location.href = '/';
                        return;
                    }
                    
                    // Validation error
                    if (responseData?.errors) {
                        if (responseData.errors.password && passwordField) {
                            passwordField.classList.add('is-invalid');
                            passwordField.focus();
                            
                            if (errorMessage) {
                                const passwordError = Array.isArray(responseData.errors.password) 
                                    ? responseData.errors.password[0] 
                                    : responseData.errors.password;
                                
                                // Try to translate if it looks like a translation key (contains dots)
                                // Otherwise use the message directly (already translated from server)
                                let errorText = passwordError;
                                if (passwordError && passwordError.includes('.') && t) {
                                    // Might be a translation key, try to translate it
                                    const translated = t(passwordError, passwordError);
                                    if (translated && translated !== passwordError) {
                                        errorText = translated;
                                    }
                                }
                                
                                // Fallback to generic error if message is empty
                                if (!errorText || errorText.trim() === '') {
                                    errorText = t?.('auth.password', 'The provided password is incorrect.') || 'The provided password is incorrect.';
                                }
                                
                                errorMessage.textContent = errorText;
                                errorMessage.classList.remove('d-none');
                            }
                        } else if (responseData.errors && Object.keys(responseData.errors).length > 0) {
                            // Other validation errors - show first one
                            const firstErrorKey = Object.keys(responseData.errors)[0];
                            const firstError = Array.isArray(responseData.errors[firstErrorKey]) 
                                ? responseData.errors[firstErrorKey][0] 
                                : responseData.errors[firstErrorKey];
                            showError(firstError);
                        }
                    } else if (responseData?.message) {
                        showError(responseData.message);
                    } else {
                        // No specific error message - show generic error
                        showError();
                    }
                } else {
                    // HTML response - likely a redirect
                    window.location.href = '/';
                }
            } catch (error) {
                resetButton();
                
                // Handle validation errors (422)
                if (error?.status === 422) {
                    try {
                        const data = error.response || (error.responseText ? JSON.parse(error.responseText) : {});
                        if (data?.errors?.password) {
                            const passwordError = Array.isArray(data.errors.password) 
                                ? data.errors.password[0] 
                                : data.errors.password;
                            
                            // Try to translate if it looks like a translation key (contains dots)
                            let errorText = passwordError;
                            if (passwordError && passwordError.includes('.') && t) {
                                const translated = t(passwordError, passwordError);
                                if (translated && translated !== passwordError) {
                                    errorText = translated;
                                }
                            }
                            
                            // Fallback to generic error if message is empty
                            if (!errorText || errorText.trim() === '') {
                                errorText = t?.('auth.password', 'The provided password is incorrect.') || 'The provided password is incorrect.';
                            }
                            
                            showError(errorText);
                        } else if (data?.message) {
                            showError(data.message);
                        } else {
                            // Generic error - try to get error message from error
                            let errorMsg = t?.('common.error_occurred', 'An error occurred') || 'An error occurred';
                            if (error?.responseText) {
                                try {
                                    const errorData = JSON.parse(error.responseText);
                                    if (errorData?.message) {
                                        errorMsg = errorData.message;
                                    }
                                } catch (e) {
                                    // Ignore parse errors
                                }
                            }
                            showError(errorMsg);
                        }
                    } catch (e) {
                        // Parse error - show generic message
                        showError(t?.('common.error_occurred', 'An error occurred') || 'An error occurred');
                    }
                } else {
                    // Network or other error
                    let networkErrorMsg = t?.('common.error_occurred', 'An error occurred') || 'An error occurred';
                    if (error?.message) {
                        networkErrorMsg = error.message;
                    } else if (typeof error === 'string') {
                        networkErrorMsg = error;
                    }
                    showError(networkErrorMsg);
                }
            }
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // Safety mechanism: Ensure loading spinner is hidden after maximum wait time
    // This prevents indefinite loading if something goes wrong
    let maxWaitTimeout = setTimeout(() => {
        const loadingView = document.getElementById('loadingView');
        const userSelectionView = document.getElementById('userSelectionView');
        if (loadingView && !loadingView.classList.contains('d-none')) {
            // Force hide loading and show user selection as fallback
            toggleVisibility?.('loadingView', false, 'd-block', 'd-none');
            toggleVisibility?.('userSelectionView', true, 'd-block', 'd-none');
            showUserSelection([]); // Show "Other" option as fallback
        }
    }, (TimingConstants?.WELCOME_USER_LOADING_TIMEOUT || 10000) + 5000); // 15 seconds total (10s + 5s buffer)
    
    // Helper function to clear max wait timeout
    function clearMaxWaitTimeout() {
        if (maxWaitTimeout) {
            clearTimeout(maxWaitTimeout);
            maxWaitTimeout = null;
        }
    }
    
    try {
        // Initialize i18n with translations from window
        if (typeof window !== 'undefined' && window.appTranslations && window.appLocale && initI18n) {
            initI18n(window.appTranslations, window.appLocale);
        }
        
        initializeConfig();
    
        // Setup autofocus for password login modal (works for both data-attribute and programmatic opens)
        if (setupModalFocus && TimingConstants) {
            setupModalFocus('passwordLoginModal', '#passwordLoginModalPassword', TimingConstants.MODAL_FOCUS_DELAY);
        }
        
        // Setup Bootstrap modal handler (user tiles use Bootstrap's native modal functionality)
        setupModalDataHandler();
        
        // Setup event handler for "Forgot password" link
        const forgotPasswordLink = document.querySelector('[data-store-password-form-state]');
        if (forgotPasswordLink) {
            forgotPasswordLink.addEventListener('click', (event) => {
                event.preventDefault();
                storePasswordFormState();
                const href = forgotPasswordLink.getAttribute('href');
                if (href) {
                    window.location.href = href;
                }
            });
        }
        
        // Setup event handler for back button
        const welcomeBackBtn = document.getElementById('welcomeBackBtn');
        if (welcomeBackBtn) {
            welcomeBackBtn.addEventListener('click', () => {
                showUserSelection(registeredUsers);
            });
        }
        
        setupFormHandlers();
        
        // Check for duplicate device error toast (stored in body dataset)
        // If duplicate error exists, handle it and DON'T run checkRegisteredUsers() to prevent redirect
        const duplicateDeviceErrorAttr = document.body.dataset.duplicateDeviceError;
        if (duplicateDeviceErrorAttr) {
            try {
                const duplicateDeviceError = JSON.parse(duplicateDeviceErrorAttr);
                // If there's a duplicate device error, show toast and ensure user selection is shown
                if (duplicateDeviceError.email && duplicateDeviceError.device_name) {
                    // Show toast notification
                    const message = duplicateDeviceError.message || t?.('messages.device_already_registered') || 'Device already registered';
                    showDuplicateDeviceToast(
                        message,
                        duplicateDeviceError.email,
                        duplicateDeviceError.username || duplicateDeviceError.email, // Use username if available, fallback to email
                        duplicateDeviceError.device_name
                    );
                
                    // Fetch registered users to ensure the user from duplicate error is in the array
                    fetchAndShowRegisteredUsers();
                } else {
                    // Generic duplicate error without user info
                    const message = duplicateDeviceError.message || t?.('messages.device_already_registered') || 'Device already registered';
                    showDuplicateDeviceToast(
                        message,
                        duplicateDeviceError.email || '',
                        duplicateDeviceError.username || '',
                        duplicateDeviceError.device_name || ''
                    );
                    
                    // Still try to fetch and show users if available
                    fetchAndShowRegisteredUsers();
                }
                
                // Clear duplicate error from body dataset
                delete document.body.dataset.duplicateDeviceError;
                
                // Don't continue with normal checkRegisteredUsers() flow - return early
                return;
            } catch (e) {
                // Silently handle duplicate device error parsing
            }
        }
        
        // Check if there are password login modal validation errors and show modal
        const passwordLoginModalField = document.getElementById('passwordLoginModalPassword');
        const passwordLoginModal = document.getElementById('passwordLoginModal');
        if (passwordLoginModalField?.classList.contains('is-invalid') && passwordLoginModal) {
            // Get email, username, and device name from hidden inputs and display
            const modalEmailInput = document.getElementById('passwordLoginModalEmail');
            const modalEmail = modalEmailInput?.value || '';
            const modalDeviceNameInput = document.getElementById('passwordLoginModalDeviceName');
            const modalDeviceName = modalDeviceNameInput?.value || '';
            const modalUsernameDisplay = document.getElementById('passwordLoginModalUsernameDisplay');
            const username = modalUsernameDisplay?.textContent ? modalUsernameDisplay.textContent.split(' (')[0] : ''; // Extract username from display
            
            if (modalEmail) {
                // Get profile picture from the modal if available
                const modalProfilePicture = document.getElementById('passwordLoginModalProfilePicture');
                const profileImg = modalProfilePicture?.querySelector('img');
                let profilePictureFilename = '';
                if (profileImg?.src) {
                    const urlParts = profileImg.src.split('/');
                    profilePictureFilename = urlParts[urlParts.length - 1];
                    try {
                        profilePictureFilename = decodeURIComponent(profilePictureFilename);
                    } catch (e) {
                        // If decoding fails, use as-is
                    }
                }
                
                // Open modal with existing error state
                openPasswordLoginModal(modalEmail, username, modalDeviceName, profilePictureFilename);
            }
        }
        
        // Check if we should restore password modal (returning from forgot password)
        const shouldRestorePasswordModal = SessionConstants && sessionStorage.getItem(SessionConstants.RESTORE_PASSWORD_MODAL) === 'true';
        const storedEmail = shouldRestorePasswordModal && SessionConstants ? sessionStorage.getItem(SessionConstants.PASSWORD_FORM_EMAIL) : null;
        // Backward compatibility: try old username key if email not found
        const storedUsername = shouldRestorePasswordModal && !storedEmail && SessionConstants ? sessionStorage.getItem(SessionConstants.PASSWORD_FORM_USERNAME) : null;
        
        if (shouldRestorePasswordModal && (storedEmail || storedUsername)) {
            // Restore password modal state from sessionStorage
            const storedDeviceName = SessionConstants ? sessionStorage.getItem(SessionConstants.PASSWORD_FORM_DEVICE_NAME) : null;
            const storedProfilePicture = SessionConstants ? sessionStorage.getItem(SessionConstants.PASSWORD_FORM_PROFILE_PICTURE) : null;
            
            // Ensure device UID is ready, then restore modal and fetch users
            ensureDeviceUidInForms();
            
            // Clear restore flag
            if (SessionConstants) {
                sessionStorage.removeItem(SessionConstants.RESTORE_PASSWORD_MODAL);
            }
            
            // Fetch registered users to get email if we only have username (backward compatibility)
            if (registeredUsersRoute && moduleCsrfToken) {
                fetchRegisteredUsers?.(deviceUid, registeredUsersRoute, moduleCsrfToken)
                    .then((response) => {
                        registeredUsers = extractUsersFromResponse(response);
                        
                        // If we only have username (old session storage), look up email
                        let email = storedEmail || '';
                        let username = storedUsername || '';
                        if (!email && username && registeredUsers.length > 0) {
                            const user = registeredUsers.find(u => u.username === username);
                            if (user) {
                                email = user.email || '';
                            }
                        }
                        
                        // Hide loading spinner since we have users or know there are none
                        toggleVisibility?.('loadingView', false, 'd-block', 'd-none');
                        
                        // Open modal with stored state
                        openPasswordLoginModal(email, username, storedDeviceName, storedProfilePicture);
                    })
                    .catch(() => {
                        // Hide loading spinner even on error
                        toggleVisibility?.('loadingView', false, 'd-block', 'd-none');
                        
                        // Open modal anyway (email lookup will fail, but show modal)
                        openPasswordLoginModal(storedEmail || '', storedUsername || '', storedDeviceName, storedProfilePicture);
                    });
            } else {
                // No API route, open modal with what we have
                openPasswordLoginModal(storedEmail || '', storedUsername || '', storedDeviceName, storedProfilePicture);
            }
            
            // Clear stored state after opening modal
            if (SessionConstants) {
                sessionStorage.removeItem(SessionConstants.PASSWORD_FORM_EMAIL);
                sessionStorage.removeItem(SessionConstants.PASSWORD_FORM_USERNAME);
                sessionStorage.removeItem(SessionConstants.PASSWORD_FORM_DEVICE_NAME);
                sessionStorage.removeItem(SessionConstants.PASSWORD_FORM_PROFILE_PICTURE);
            }
        } else {
            // Normal flow - check for registered users
            checkRegisteredUsers();
        }
        
        // Clear max wait timeout once checkRegisteredUsers is called (it has its own timeout)
        clearMaxWaitTimeout();
    } catch (e) {
        console.error('[Welcome] Error during initialization:', e);
        
        // Clear max wait timeout since we're handling the error
        clearMaxWaitTimeout();
        
        // Fallback: hide loading spinner and show empty user selection with "Other" option
        try {
            toggleVisibility?.('loadingView', false, 'd-block', 'd-none');
            showUserSelection([]);
        } catch (innerError) {
            console.error('[Welcome] Secondary error:', innerError);
        }
    }

    // Check for registration success parameter and open pending approval modal
    checkRegistrationSuccess();
});

/**
 * Check for registration success and open pending approval modal
 */
function checkRegistrationSuccess() {
    try {
        const urlParams = new URLSearchParams(window.location.search);
        const registered = urlParams.get('registered');
        
        if (registered === '1') {
            // Use requestAnimationFrame to ensure DOM is ready
            requestAnimationFrame(() => {
                const modalElement = document.getElementById('pendingApprovalModal');
                if (!modalElement) {
                    // Modal not found, wait a bit and retry
                    setTimeout(() => {
                        const retryModal = document.getElementById('pendingApprovalModal');
                        if (retryModal) {
                            openPendingApprovalModal();
                        }
                    }, TimingConstants?.MODAL_RETRY_DELAY || 200);
                    return;
                }
                
                openPendingApprovalModal();
            });
        }
    } catch (e) {
        // Silently handle errors
    }
}

/**
 * Open the pending approval modal
 */
function openPendingApprovalModal() {
    const modalElement = document.getElementById('pendingApprovalModal');
    if (!modalElement) {
        return;
    }
    
    // Check if Bootstrap is available
    if (typeof window === 'undefined' || !window.bootstrap?.Modal) {
        return;
    }
    
    // Get or create modal instance
    let modalInstance = window.bootstrap.Modal.getInstance(modalElement);
    if (!modalInstance) {
        // Create new instance with static backdrop
        modalInstance = new window.bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false,
            focus: true
        });
    } else {
        // Update options if instance already exists
        modalInstance._config.backdrop = 'static';
        modalInstance._config.keyboard = false;
    }
    
    // Listen for when modal is fully shown to verify it's visible
    const handleShown = () => {
        // Verify modal has show class and is visible
        if (!modalElement.classList.contains('show')) {
            modalElement.classList.add('show');
        }
        
        // Ensure modal-dialog is properly displayed
        const modalDialog = modalElement.querySelector('.modal-dialog');
        if (modalDialog) {
            modalDialog.classList.add('modal-dialog-centered');
        }
        
        // Clean up URL parameter after modal is shown
        const newUrl = window.location.pathname + (window.location.hash || '');
        window.history.replaceState({}, document.title, newUrl);
        
        // Remove listener after first use
        modalElement.removeEventListener('shown.bs.modal', handleShown);
    };
    
    modalElement.addEventListener('shown.bs.modal', handleShown, { once: true });
    
    // Show the modal
    modalInstance.show();
}
