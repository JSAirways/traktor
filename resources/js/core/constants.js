/**
 * Application Constants
 * 
 * Centralizes magic strings and special values used throughout the application
 * to improve code clarity and maintainability.
 */

/**
 * Device-related constants
 */
export const DeviceConstants = {
    /**
     * Special device name value used to indicate a password-only login
     * (not a full device registration).
     * 
     * This value is used as a flag in the device_name field to distinguish
     * between password-only logins and full device registrations.
     */
    PASSWORD_ONLY_LOGIN_FLAG: 'password-login',
    
    /**
     * Default device name when no name is provided.
     */
    DEFAULT_DEVICE_NAME: 'Unnamed Device'
};

/**
 * Session storage key constants
 */
export const SessionConstants = {
    /**
     * Session storage key for password form email (for authentication)
     */
    PASSWORD_FORM_EMAIL: 'passwordFormEmail',
    
    /**
     * Session storage key for password form username (for display, backward compatibility)
     * @deprecated Use PASSWORD_FORM_EMAIL instead
     */
    PASSWORD_FORM_USERNAME: 'passwordFormUsername',
    
    /**
     * Session storage key for password form device name
     */
    PASSWORD_FORM_DEVICE_NAME: 'passwordFormDeviceName',
    
    /**
     * Session storage key for password form profile picture
     */
    PASSWORD_FORM_PROFILE_PICTURE: 'passwordFormProfilePicture',
    
    /**
     * Session storage key for restore password modal flag
     */
    RESTORE_PASSWORD_MODAL: 'restorePasswordModal'
};

/**
 * Timing constants for delays, timeouts, and intervals
 * Centralizes magic numbers to improve maintainability
 */
export const TimingConstants = {
    /**
     * Auto-hide delay for video player controls (milliseconds)
     */
    AUTO_HIDE_DELAY: 3000,
    
    /**
     * Progress update interval for video player (milliseconds)
     */
    PROGRESS_UPDATE_INTERVAL: 100,
    
    /**
     * Double-click/tap delay threshold (milliseconds)
     */
    DOUBLE_CLICK_DELAY: 300,
    
    /**
     * Touch duration threshold for tap detection (milliseconds)
     */
    TOUCH_DURATION_THRESHOLD: 200,
    
    /**
     * Modal focus delay to ensure DOM is ready (milliseconds)
     */
    MODAL_FOCUS_DELAY: 150,
    
    /**
     * Modal profile picture setup delay (milliseconds)
     */
    MODAL_PROFILE_PICTURE_DELAY: 50,
    
    /**
     * Mobile viewport fix delay (milliseconds)
     */
    MOBILE_VIEWPORT_FIX_DELAY: 100,
    
    /**
     * Registration success modal retry delay (milliseconds)
     */
    MODAL_RETRY_DELAY: 200,
    
    /**
     * Welcome page user loading timeout (milliseconds)
     */
    WELCOME_USER_LOADING_TIMEOUT: 10000,
    
    /**
     * Gallery channel filter debounce delay (milliseconds)
     */
    GALLERY_FILTER_DEBOUNCE: 150,
    
    /**
     * Orientation change delay for viewport fix (milliseconds)
     */
    ORIENTATION_CHANGE_DELAY: 100,
    
    /**
     * Wake lock retry delay after automatic release (milliseconds)
     * Used when wake lock is released but video is still playing
     */
    WAKE_LOCK_RETRY_DELAY: 1000,
    
    /**
     * Wake lock visibility delay when page becomes visible (milliseconds)
     * Small delay to ensure page is fully visible before re-requesting wake lock
     */
    WAKE_LOCK_VISIBILITY_DELAY: 100
};
