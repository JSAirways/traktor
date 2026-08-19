# Best Practices Rulebook - Traktor v2

**Purpose:** This document provides rules, patterns, and guidelines for active development of the Traktor v2 application (Laravel 12). It defines how to organize code, what patterns to follow, and when to use specific approaches.

**Note:** For technical documentation of existing features, architecture, and system behavior, refer to the **Technical Brief**.

## Table of Contents

1. [File Organization](#file-organization)
2. [Naming Conventions](#naming-conventions)
3. [JavaScript Standards](#javascript-standards)
4. [Blade Component Standards](#blade-component-standards)
5. [SCSS Standards](#scss-standards)
6. [Bootstrap Integration](#bootstrap-integration)
7. [Progressive Web App (PWA)](#progressive-web-app-pwa)
8. [State Management](#state-management)
9. [Laravel Backend Standards](#laravel-backend-standards)
10. [Code Quality](#code-quality)
11. [Documentation Requirements](#documentation-requirements)

---

## File Organization

### JavaScript Structure

```
resources/js/
├── core/
│   ├── bootstrap.js      # Application initialization
│   ├── bootstrap-js.js   # Bootstrap JavaScript initialization
│   ├── state.js          # Global state management (appState)
│   ├── events.js         # Event emitter system
│   ├── utils.js          # Utility functions
│   ├── view-helpers.js   # View state utility functions (isInPlayerView, isInGalleryView, getCurrentView)
│   ├── pwa-installer.js  # PWA service worker registration
│   ├── asset-version-checker.js  # Asset version detection and cache clearing
│   ├── cache-version-monitor.js  # Cache version monitoring and automatic page reload
│   ├── constants.js      # JavaScript constants (TimingConstants)
│   ├── device-identity.js  # Device UID, capabilities, PS4-safe UUID
│   ├── device-api.js     # Device API integration
│   ├── error-handler.js  # Standardized error handling
│   ├── loading-state-manager.js  # Loading state management
│   ├── modal-utils.js    # Modal utilities
│   ├── analytics-tracker.js  # Analytics event tracking (event-based)
│   ├── wake-lock.js      # Screen wake lock during playback
│   ├── orientation.js    # Orientation / layout helpers
│   ├── i18n.js          # Internationalization
│   └── namespace.js     # Global Traktor namespace (legacy compat; uses IIFE)
├── modules/
│   ├── video-player.js   # Video player functionality
│   ├── gallery.js        # Gallery view management
│   ├── gallery-channels.js # Channel filtering and content type filtering
│   ├── playlist.js       # Playlist management
│   ├── playlist-state-manager.js  # Centralized playlist state management (single source of truth)
│   ├── controls.js       # Player controls
│   ├── navbar.js         # Navbar behavior
│   ├── fullscreen.js     # Fullscreen functionality
│   └── view-switcher.js  # View state management
├── resources/            # Resource-based organization
│   ├── accounts/
│   │   └── forgot-password.js
│   ├── galleries/
│   │   └── index.js      # Gallery page (/{slug}/gallery)
│   ├── pins/
│   │   └── entry.js      # PIN modal logic (loaded on profiles/selection)
│   ├── player/
│   │   └── show.js       # Player page initialization
│   ├── profiles/
│   ├── shared/            # Cross-resource utilities
│   │   ├── locale-switcher.js  # Locale switcher (no-op, kept for backward compatibility)
│   │   ├── options-menu-offcanvas.js
│   │   └── profile-picture-selector.js
│   └── welcome/
│       └── index.js      # Welcome page logic
└── admin/                # Admin-specific resources
    ├── content/
    │   ├── index.js      # Content management UI
    │   └── channel-import.js  # Channel import functionality
    ├── dashboard/
    │   └── index.js      # Analytics dashboard UI
    ├── settings/
    │   └── quota-monitor.js  # YouTube quota monitoring UI
    ├── shared/           # Admin shared utilities
    │   ├── admin-forms.js
    │   ├── admin-layout.js
    │   ├── admin-password-modal.js
    │   └── bulk-actions.js  # Bulk operations utility
    └── users/
        └── pending.js    # Pending users bulk operations
```

**Rules:**
- All JavaScript files must use **kebab-case** naming
- Core modules contain shared, foundational code
- Feature modules contain reusable, stateful functionality
- Resources are organized by resource name (accounts, devices, galleries, pins, player, profiles, welcome)
- Admin resources are separated in `admin/` namespace
- Shared utilities across resources go in `resources/shared/`
- `app.js` should only handle initialization and high-level coordination
- **Prefer native ES6 `import`/`export` syntax** for new and touched modules
- **Avoid new IIFE wrappers or global namespace assignments** — legacy `window.Traktor` attachments remain in some modules during transition (`namespace.js`, `device-identity.js`, etc.)
- **Relative import paths** (e.g., `../../core/utils.js`)
- **PS4 browser minimum requirement** (ES2020 support)
- **Legacy:** `galleries/show.js` exists but is unused; the gallery route uses `galleries/index.js`

### Blade Component Structure

```
resources/views/
├── accounts/              # Account resource
│   ├── register.blade.php
│   ├── rejected.blade.php
│   └── pending-approval.blade.php
├── admin/                 # Admin resources (resource-based)
│   ├── children/
│   │   ├── create.blade.php
│   │   ├── edit.blade.php
│   │   └── index.blade.php
│   ├── dashboard/
│   │   ├── index.blade.php  # Main analytics dashboard
│   │   ├── user.blade.php   # User-specific dashboard view
│   │   ├── users.blade.php  # Admin user list view
│   │   ├── _panel.blade.php # Dashboard panel partial
│   │   └── _scripts.blade.php
│   ├── content/
│   │   └── index.blade.php
│   ├── devices/
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   └── partials/
│   │       ├── capability-badges.blade.php
│   │       └── capability-panel.blade.php
│   ├── parent-devices/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   ├── settings/
│   │   └── edit.blade.php
│   └── users/
│       ├── create.blade.php
│       ├── edit.blade.php
│       ├── index.blade.php
│       ├── pending.blade.php
│       └── show-pin.blade.php
├── components/            # Reusable components
│   ├── admin/
│   │   ├── content/
│   │   │   ├── bulk-actions-toolbar.blade.php
│   │   │   ├── channel-header.blade.php
│   │   │   ├── channel-section.blade.php
│   │   │   ├── content-row.blade.php
│   │   │   ├── content-table-header.blade.php
│   │   │   └── playlist-video-row.blade.php
│   │   └── page-header.blade.php
│   ├── gallery/
│   │   ├── channel-header.blade.php
│   │   ├── channel-sidebar.blade.php
│   │   ├── content-filter-pills.blade.php
│   │   ├── content-header.blade.php
│   │   ├── playlist-header.blade.php
│   │   ├── playlist-tile.blade.php
│   │   ├── single-column-layout.blade.php
│   │   ├── video-tile.blade.php
│   │   └── view.blade.php
│   ├── layout/
│   │   ├── navbar.blade.php
│   │   └── navbar-gallery.blade.php
│   ├── forms/
│   │   ├── form-field.blade.php
│   │   ├── form-group.blade.php
│   │   ├── pin-field.blade.php
│   │   ├── profile-picture-selector.blade.php
│   │   ├── welcome-forgot-password-form.blade.php
│   │   └── welcome-password-form.blade.php
│   ├── modals/
│   │   ├── admin-password-modal.blade.php
│   │   ├── channel-import-modal.blade.php
│   │   ├── modal-base.blade.php
│   │   ├── password-login-modal.blade.php
│   │   ├── pending-approval-modal.blade.php
│   │   └── pin-entry-modal.blade.php
│   ├── player/
│   │   ├── control-bar.blade.php
│   │   ├── structure.blade.php
│   │   ├── video-container.blade.php
│   │   └── view.blade.php
│   ├── ui/
│   │   ├── back-button.blade.php
│   │   ├── flash-messages.blade.php
│   │   ├── loading-spinner.blade.php
│   │   ├── locale-switcher.blade.php
│   │   ├── options-menu-offcanvas.blade.php
│   │   ├── pwa-install-button.blade.php
│   │   ├── table-accordion-button.blade.php
│   │   ├── toast-notification-template.blade.php
│   │   ├── toast-notification.blade.php
│   │   ├── user-avatar.blade.php
│   │   ├── user-selection-grid.blade.php
│   │   ├── user-selector.blade.php
│   │   ├── form-action-button.blade.php
│   │   └── form-action-link.blade.php
│   ├── emails/
│   │   ├── button.blade.php
│   │   └── layout.blade.php
│   └── welcome/
│       ├── user-tile-template.blade.php
│       └── welcome-user-selection.blade.php
├── layouts/               # Layout templates
│   ├── admin.blade.php
│   ├── app.blade.php
│   ├── frontend.blade.php
│   └── guest.blade.php
├── galleries/             # Gallery resource (index = active; show = legacy)
│   ├── index.blade.php    # Used by GalleryController@show
│   └── show.blade.php     # Legacy — not rendered
├── pins/                  # PIN resource (entry view legacy; modal on profiles/selection)
│   └── entry.blade.php
├── player/                 # Player resource
│   ├── _partial.blade.php
│   └── show.blade.php
├── profiles/              # Profile resource
│   └── selection.blade.php
└── welcome/               # Welcome resource
    └── index.blade.php
```

**Rules:**
- All component files must use **kebab-case** naming
- Organize by resource (accounts, devices, galleries, pins, player, profiles, welcome)
- Admin resources are organized by resource in `admin/` namespace
- Reusable components are in `components/` organized by type
- Resource-specific components should be in `components/{resource}/` to follow Laravel's automatic component discovery
- Layout templates are in `layouts/`
- Component names must match file names exactly
- Use descriptive names that clearly indicate purpose

### SCSS Structure

```
resources/css/scss/
├── _variables.scss       # Colors, spacing, typography
├── layout/
│   ├── _navbar.scss
│   └── _gallery.scss
├── components/
│   ├── _forms.scss
│   ├── _modals.scss
│   ├── _profile-picture-selector.scss
│   ├── _options-menu-offcanvas.scss
│   ├── _shared.scss
│   └── _toast-notification.scss
├── resources/            # Resource-based organization
│   ├── galleries/
│   │   ├── _index.scss
│   │   └── _show.scss
│   ├── pins/
│   │   └── _entry.scss
│   ├── player/
│   │   └── _show.scss
│   ├── profiles/
│   │   └── _selection.scss
│   ├── video-player/
│   │   └── _video-player.scss
│   └── welcome/
│       └── _index.scss
└── admin/                # Admin-specific resources
    ├── _admin.scss
    ├── _dashboard.scss   # Analytics dashboard styles
    └── content/
```

**Rules:**
- Use `@use` for SCSS module imports
- Import order: variables → layout → components → resources → admin
- Resources are organized by resource name
- Admin resources are separated in `admin/` namespace
- Create component-specific SCSS files that match Blade component names
- Use `_shared.scss` only for truly shared utilities across multiple components
- Keep component-specific styles in dedicated files

---

## Naming Conventions

### JavaScript

| Type | Convention | Example |
|------|-----------|---------|
| **Files** | kebab-case | `video-player.js`, `view-switcher.js` |
| **Variables** | camelCase | `currentView`, `isPlaying` |
| **Functions** | camelCase | `showGallery()`, `formatTime()` |
| **Classes** | PascalCase | `Gallery`, `VideoPlayer` |
| **Constants** | UPPER_SNAKE_CASE | `MAX_RETRIES`, `API_BASE_URL` |
| **Global Namespace** | PascalCase | `Traktor.Core.utils`, `Traktor.Admin.channelImport` |

**Rules:**
- Use ES6 module exports (no global namespace needed)
- Use descriptive names that indicate purpose
- Avoid abbreviations unless universally understood
- Follow ES6 module naming conventions

### CSS Classes

| Type | Convention | Example |
|------|-----------|---------|
| **Classes** | kebab-case | `view-gallery`, `navbar-container` |
| **View States** | `view-{name}` | `view-gallery`, `view-player` |
| **Component Variants** | `{component}-{variant}` | `navbar-primary`, `profile-header-compact` |
| **State Classes** | `is-{state}` | `is-active`, `is-hidden`, `is-loading` |

**Rules:**
- Use descriptive, semantic class names
- Avoid inline styles - use utility classes or SCSS
- Use Bootstrap utility classes for common patterns
- Follow BEM-inspired naming for complex components

### Blade/PHP

| Type | Convention | Example |
|------|-----------|---------|
| **Component Files** | kebab-case | `admin-password-modal.blade.php` |
| **PHP Variables** | camelCase | `$userName`, `$isActive` |
| **Component Props** | kebab-case | `<x-component :user-name="...">` |
| **Component Names** | kebab-case | matches file name |
| **Controller Properties** | camelCase (via constructor promotion) | `protected YouTubeService $youtubeService` |

**Rules:**
- Props use kebab-case (Laravel convention)
- PHP variables use camelCase
- Component names must match file names exactly
- Use constructor promotion for controller dependencies (Laravel 12 feature)

### HTML Attributes

| Type | Convention | Example |
|------|-----------|---------|
| **IDs** | camelCase | `kidsSelectionBtn`, `videoPlayer` |
| **Data Attributes** | kebab-case | `data-bs-toggle`, `data-user-id` |
| **Classes** | kebab-case | `btn-primary`, `container-fluid` |

---

## JavaScript Standards

### Module Organization

**Native ES6 Modules (PS4 Minimum Requirement):**
```javascript
// Import dependencies
import { utils } from '../core/utils.js';
import { appState } from '../core/state.js';

// Module code
export function myFunction() {
    // Implementation
}

export class MyModule {
    // Implementation
}

// Default export (if needed)
export default MyModule;
```

**Rules:**
- **Prefer native ES6 `import`/`export` syntax**
- **Avoid new IIFE wrappers or global namespace assignments** (legacy `window.Traktor` still used in places)
- Use relative import paths (e.g., `../../core/utils.js`)
- Modules are tree-shakeable and optimized by Vite
- PS4 browser minimum requirement (ES2020 support)
- All modern JavaScript features available (arrow functions, template literals, optional chaining, async/await)

### State Management

**Rules:**
- Use `appState` as the single source of truth for global application state
- Use `PlaylistStateManager` as the single source of truth for playlist-specific state
- Subscribe to state changes rather than polling
- Centralize state management in `core/state.js`
- Use `eventEmitter` for cross-module communication
- View state should be managed by `view-switcher.js`
- **Playlist state operations**: Always use `PlaylistStateManager` methods instead of direct `appState` access for playlist-related state
- **View-aware operations**: Use `isInPlayerView()` and `isInGalleryView()` helpers before modifying state that affects playback

**Example:**
```javascript
// Subscribe to state changes
appState.on('currentView', (view) => {
    // Handle view change
});

// Update state
appState.currentView = 'gallery';
```

### Event-Driven Architecture

**Rules:**
- Use `eventEmitter` for cross-module communication
- Emit events for significant state changes
- Subscribe to events rather than direct function calls
- Keep event names descriptive and consistent
- **Prefer direct method calls** over events when the caller and callee are known (better performance, clearer flow)
- **Use events** for decoupled communication between modules that don't know about each other
- **Avoid duplicate event handlers** - each event should have a single, clear handler
- **Event delegation**: Use event delegation on `document` for buttons that may not be immediately available or may be hidden by CSS (e.g., landscape controls)

### DOM Manipulation

**Rules:**
- **NEVER** use `replaceWith()` or create/remove DOM elements
- **ONLY** toggle CSS classes for visibility/state
- Render all UI elements in Blade templates
- JavaScript only manages behavior and state
- **Player Structure**: All player HTML (iframe, click blocker, overlay, controls) must be in Blade templates - JavaScript only finds elements and attaches handlers
- **Component IDs**: When using the same component multiple times (e.g., filter pills in main header and landscape controls), use unique IDs via component props to avoid conflicts

**Allowed:**
```javascript
element.classList.add('d-none');
element.classList.remove('d-block');
```

**Forbidden:**
```javascript
element.replaceWith(newElement);
document.createElement('button');
element.innerHTML = '<button>...</button>';
```

**Exceptions (with documentation):**
- **Loading server-rendered HTML**: `innerHTML` may be used to insert server-rendered HTML from API responses
- **Bootstrap backdrop cleanup**: `remove()` may be used to clean up duplicate Bootstrap backdrop elements

### Code Structure

**Rules:**
- Keep `app.js` minimal - only initialization
- Move page-specific logic to `resources/{resource}/` modules
- Keep modules focused on single responsibilities
- Avoid circular dependencies
- Use proper error handling
- Cache frequently accessed DOM elements
- Use constants for magic numbers (timing values, delays, intervals)
- Use centralized utilities for common patterns (loading states, error handling)

### DOM Element Caching

**Rules:**
- Cache DOM elements that are accessed multiple times
- Query elements once in constructor/initialization, store in variables
- Use cached elements instead of repeated `getElementById()`/`querySelector()` calls

**Pattern:**
```javascript
// Good: Cache elements
function MyComponent() {
    this.button = document.getElementById('myButton');
    this.container = document.querySelector('.my-container');
    this.init();
}

// Bad: Repeated queries
function handleClick() {
    const button = document.getElementById('myButton'); // Query every time
}
```

### JavaScript Constants

**Rules:**
- Use `TimingConstants` from `core/constants.js` for all timing values
- Never use magic numbers for delays, timeouts, or intervals
- Centralizes timing values for easy maintenance

**Available Constants:**
- `TimingConstants.AUTO_HIDE_DELAY` - Auto-hide delay for video player controls (3000ms)
- `TimingConstants.PROGRESS_UPDATE_INTERVAL` - Progress update interval (100ms)
- `TimingConstants.DOUBLE_CLICK_DELAY` - Double-click/tap delay threshold (300ms)
- `TimingConstants.MODAL_FOCUS_DELAY` - Modal focus delay (150ms)
- And more...

### Loading State Management

**Rules:**
- Use `LoadingStateManager` from `core/loading-state-manager.js` for loading/error states
- Provides consistent patterns for showing/hiding loading spinners and error messages

### CSRF Token Handling

**Rules:**
- **Always use `makeRequest` utility** for AJAX requests - it automatically handles CSRF tokens
- **Automatic token refresh**: `makeRequest` automatically refreshes tokens on 419 errors and retries
- **Excluded routes**: Use `skipCsrf: true` option only for routes excluded from CSRF protection
- **Token utilities**: Use `getCsrfToken()`, `refreshCsrfToken()`, and `updateCsrfToken()` from `core/utils.js`
- **Never manually add CSRF headers** - let `makeRequest` handle it automatically
- See [CSRF Token Guide](../docs/CSRF_TOKEN_GUIDE.md) for complete documentation

**Example:**
```javascript
// ✅ Good: Automatic CSRF handling
const response = await makeRequest('/api/endpoint', {
    method: 'POST',
    body: { data: 'value' }
});

// ✅ Good: Excluded route
const response = await makeRequest('/admin/verify-password', {
    method: 'POST',
    body: formData,
    skipCsrf: true // Route is excluded from CSRF protection
});

// ❌ Bad: Manual CSRF token handling
xhr.setRequestHeader('X-CSRF-TOKEN', getCsrfToken());
```

### Error Handling

**Rules:**
- Use `ErrorHandler` from `core/error-handler.js` for standardized error handling
- Always log errors to console for debugging (with context)
- Show user-friendly messages (via toast or loading manager)
- **CSRF errors (419)**: Handled automatically by `makeRequest` - tokens are refreshed and request is retried

**Error Types:**
- `ErrorType.NETWORK` - Network errors
- `ErrorType.TIMEOUT` - Request timeout errors
- `ErrorType.VALIDATION` - Validation errors (422)
- `ErrorType.AUTHENTICATION` - Authentication errors (401, 403)
- `ErrorType.SERVER` - Server errors (500+)
- `ErrorType.UNKNOWN` - Unknown errors

---

## Blade Component Standards

### Server-Side Rendering

**Rules:**
- All UI elements must be rendered server-side in Blade
- JavaScript only toggles visibility/classes
- No JavaScript-generated HTML elements
- Components should work without JavaScript (progressive enhancement)

### Component Props

**Rules:**
- Use descriptive prop names
- Document all props with comments
- Use consistent default values
- Group related props logically
- Use type hints in comments

### Form Structure

**Rules:**
- **NEVER nest forms inside other forms** - HTML doesn't support nested forms
- Each form should be standalone with its own action and method
- Use separate forms for different actions
- Bulk operations should use JavaScript to create forms dynamically

### Output Escaping

**Rules:**
- Always use `{{ }}` for escaping output
- Use `{!! !!}` only when explicitly safe (avoid if possible)
- Never output user data without escaping
- **Avoid quotes in translation strings**: Don't use single quotes around placeholders to prevent HTML entity encoding

---

## SCSS Standards

### File Organization

**Rules:**
- Use `@use` for SCSS module imports
- Import order: variables → layout → components → resources → admin
- Organize by feature (layout, components, pages, features)
- Consolidate duplicate styles
- Use mixins for repeated patterns

### Variables and Mixins

**Rules:**
- **ALWAYS** prefer Bootstrap CSS variables (`var(--bs-*)`) when available
- **ONLY** create custom SCSS variables in `_variables.scss` for values truly not available in Bootstrap
- Use direct `rgba()` values for opacity - do not create opacity variables
- Use Bootstrap CSS variables for colors
- Avoid creating variables for one-off or rarely-used values
- **Use CSS custom properties** (`var(--bs-white)`) instead of hardcoded colors
- **Use `gap` property** instead of margin hacks for flexbox/grid spacing
- **Modern CSS transforms** are fully supported (no restrictions)

---

## Bootstrap Integration

### Utility Classes

**Rules:**
- Use Bootstrap utility classes for common patterns
- Use `d-none`, `d-block`, `d-flex` for visibility
- Use `d-sm-*`, `d-md-*` for responsive behavior
- Prefer utilities over custom CSS when possible

### Data Attributes

**Rules:**
- Use `data-bs-*` for Bootstrap functionality
- Follow Bootstrap conventions for data attributes
- Use kebab-case for data attribute names

---

## Progressive Web App (PWA)

### File Structure

```
public/
├── site.webmanifest      # Web app manifest
├── sw.js                 # Service worker
├── favicon.ico
├── favicon.svg
├── apple-touch-icon.png
├── web-app-manifest-192x192.png
└── web-app-manifest-512x512.png
```

**Rules:**
- Service worker must be in `public/` directory (root scope)
- Manifest file must be accessible at `/site.webmanifest`
- Icons must be in `public/` directory
- All PWA files must be served over HTTPS (or localhost for development)
- Modern browser API support (PS4 minimum requirement)

### Web App Manifest

**Rules:**
- Manifest file must be valid JSON
- Include `name`, `short_name`, `start_url`, `display`, `theme_color`, `background_color`
- Provide icons in 192x192 and 512x512 sizes
- Use `maskable` purpose for icons to support adaptive icons
- Set `display: "standalone"` for app-like experience

### Service Worker

**Rules:**
- Service worker file must be named `sw.js` and placed in `public/` directory
- Use cache-first strategy for static assets
- Use network-first strategy for API calls
- Implement cache versioning for updates
- Handle service worker updates gracefully
- Clean up old caches on activation
- **Use async/await** instead of promise chains
- **Modern browser APIs** (URL constructor) are natively supported

---

## State Management

### View State Management

**Rules:**
- Use `view-switcher.js` to manage view state
- Subscribe to `appState.currentView` changes
- Update body classes (`view-gallery`, `view-player`)
- Manage navbar button visibility via classes
- Keep view state centralized
- Use `isInPlayerView()` and `isInGalleryView()` helpers from `core/view-helpers.js` for view checks
- **View-aware operations**: Check current view before modifying state that affects playback

### Global State

**Rules:**
- Use `appState` as single source of truth for global application state
- Centralize state in `core/state.js`
- Use event-driven updates
- Avoid state duplication
- Keep state structure consistent

### Playlist State Management

**Rules:**
- Use `PlaylistStateManager` as single source of truth for playlist state
- **Never directly modify** `currentPlaylistId`, `currentPlaylistVideos`, or `currentVideoIndex` in `appState`
- Always use `PlaylistStateManager` methods: `setPlaylist()`, `clearPlaylist()`, `incrementIndex()`, `decrementIndex()`
- `PlaylistStateManager` enforces view-aware rules (won't clear state during playback)
- All modules (`playlist.js`, `gallery.js`, `view-switcher.js`) use `PlaylistStateManager` for playlist state operations

---

## Laravel Backend Standards

### Controller Organization

**Principles:**
- Organize controllers by **functional domain**, not abstract resources
- Each controller should have a **single, clear responsibility**
- Controllers reflect **what the application does**, not database tables
- Use descriptive names that indicate purpose

**Controller Structure:**
```
app/Http/Controllers/
├── DeviceController.php          # Device registration & management
├── ViewingSessionController.php  # PIN validation & viewing sessions
├── GalleryController.php         # Gallery viewing
├── PlayerController.php         # Video and playlist player pages
├── WelcomeController.php         # Welcome page & user selection
├── LocaleController.php          # Locale switching
├── Api/                          # API controllers
│   ├── AnalyticsController.php   # Analytics event tracking API
│   └── VideoApiController.php   # Video data API
└── Admin/                        # Admin panel controllers
    ├── DashboardController.php   # Analytics dashboard
    ├── QuotaController.php       # YouTube quota stats API
    ├── UserController.php
    ├── ChildrenController.php
    ├── ContentController.php
    ├── DeviceController.php
    ├── ParentDeviceController.php
    └── SettingController.php
```

View composers live in `app/View/Composers/` (`AppComposer`, `DeviceComposer`, `PlayerComposer`, `UserIndexComposer`; `GalleryComposer` targets legacy `galleries.show`).

**Rules:**
- Use Form Request classes for all form submissions
- Use constructor promotion for dependency injection (Laravel 12 feature)
- Inject services via constructor promotion
- Use controller traits for shared functionality
- Keep controllers thin - delegate business logic to services
- Use policies for authorization checks

### Locale Switching Pattern

**Implementation:**
- **Standard Laravel Pattern**: Form POST → Controller → Redirect (no AJAX)
- **Controller**: `LocaleController` updates session and user preference, then redirects
- **Middleware**: `SetLocale` middleware reads locale from session/user preference on each request
- **Component**: `<x-ui.locale-switcher />` uses standard form submission

**Pattern:**
```php
// Controller updates session and redirects
public function switch(Request $request): RedirectResponse
{
    $locale = $request->input('locale');
    $request->session()->put('locale', $locale);
    
    if (Auth::check()) {
        Auth::user()->update(['locale' => $locale]);
    }
    
    return redirect($this->getSafeRedirectUrl($request))
        ->with('success', __('messages.language_changed'));
}
```

**Rules:**
- **No AJAX** - Use standard Laravel form submission with redirect
- **Progressive Enhancement** - Works without JavaScript
- **Session Priority**: URL parameter → User preference → Session → Browser header
- **Safe Redirects** - Never redirect to the locale switch route itself (prevents loops)
- **Middleware Handles Locale** - Controller updates session, middleware applies locale on next request

### Constructor Promotion (Laravel 12)

**Pattern:**
```php
class ContentController extends Controller
{
    public function __construct(
        protected YouTubeService $youtubeService,
        protected ContentService $contentService
    ) {
    }
}
```

**Rules:**
- **ALWAYS** use constructor promotion for dependency injection
- Use `protected` visibility for service properties
- No need for separate property declarations
- Cleaner, more concise code
- Type-safe dependency injection

### Form Request Pattern

```php
// app/Http/Requests/Admin/StoreVideoRequest.php
class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool { /* ... */ }
    public function rules(): array { /* ... */ }
}

// In Controller
public function store(StoreVideoRequest $request)
{
    $validated = $request->validated();
    // Use validated data
}
```

### Service Dependency Injection

**Rules:**
- **ALWAYS** use constructor promotion for services
- **NEVER** use service locator pattern (`app(Service::class)`)
- All services injected via constructor promotion
- Services handle business logic, controllers orchestrate

### PHP 8.2+ Features

**Rules:**
- **Add `declare(strict_types=1);`** to new PHP files and when substantially editing existing ones (many legacy controllers predate this)
- **Use `array_is_list()`** for array validation instead of manual checks
- **Use match expressions** instead of switch where appropriate
- **Use readonly properties** for immutable configuration objects
- **Use union types** for method parameters and return types where appropriate
- **Use named arguments** for clarity in function calls with multiple parameters

**Example:**
```php
class ContentController extends Controller
{
    public function __construct(
        protected YouTubeService $youtubeService,
        protected ContentService $contentService
    ) {
    }
}
```

### Controller Traits

**Rules:**
- Use traits for shared controller functionality
- Traits should be in `app/Http/Controllers/Concerns/`
- Common traits: `HandlesPinToggle`, `HandlesProfilePicture`, `InvalidatesUserCache`

### Authorization with Policies

**Rules:**
- Use Laravel Policies for model authorization
- Consolidate similar policies (e.g., ContentPolicy for Video and Playlist)
- Use `$this->authorize()` in controllers instead of manual checks
- Policies should use `User::canManage()` for consistency
- Policies in use: `UserPolicy`, `ContentPolicy`, `SettingPolicy`

### Service Layer Pattern

**Rules:**
- Business logic belongs in Service classes
- Services are injected via constructor promotion
- Services handle external API calls, complex calculations, and data transformations
- Controllers orchestrate services and return responses
- **Centralize related logic** - avoid duplicating business logic across controllers
- **Single Responsibility** - each service should have a clear, focused purpose
- **Consistent Return Types** - services should return standardized formats

**Key Services:**
- **`DeviceTokenService`**: Signed device token management
- **`DeviceRegistrationService`**: Device registration & validation
- **`PinService`**: PIN validation helpers
- **`AssetService`**: Asset file operations
- **`ContentService`**: Content aggregation, channel management, content creation
- **`AuthenticationService`**: Centralized authentication logic
- **`UserLookupService`**: Centralized user lookup with device priority
- **`ViewingSessionService`**: Viewing session management
- **`AnalyticsService`**: Video watch analytics tracking and reporting
- **`YouTubeService`**: YouTube API integration
- **`ProfilePictureService`**: Profile picture paths and categories
- **`GoogleCloudMonitoringService`**: YouTube quota monitoring (admin settings UI)
- **`ParentalControlService`**: Exists but not enforced on viewing paths yet

### Constants Pattern

**Rules:**
- Use `app/Constants/` directory for centralizing magic strings and special values
- Create constant classes for related constants (e.g., `DeviceConstants`, `SessionConstants`)
- Use constants instead of hardcoded strings throughout the application
- Follows Laravel conventions for organizing application constants

**Structure:**
```
app/Constants/
├── DeviceConstants.php      # Device-related constants
└── SessionConstants.php     # Session storage key constants
```

### Authentication & Session Management Services

**Rules:**
- **Never duplicate user lookup logic** - always use `UserLookupService`
- **Never duplicate authentication logic** - always use `AuthenticationService`
- **Never use service locator pattern** - always inject services via constructor promotion
- **Consistent patterns** - all authentication flows use the same service methods
- **Device priority** - user lookups prioritize device-associated users, then fallback to any matching user
- **Account status validation** - centralized in `AuthenticationService::validateAccountStatus()`
- **Viewing Session Storage** - viewing sessions stored in `device_registrations` table, not Laravel session
  - Fields: `current_viewing_slug`, `viewing_validated_at`, `viewing_expires_at`
  - Persists across Laravel session regenerations (e.g., admin login)
  - Auto-created for profiles without PINs when accessed from registered device

### Response Macros

**Rules:**
- Use response macros for consistent API responses
- Register macros in `AppServiceProvider::boot()`
- Follow consistent structure: `{success: bool, message?: string, data?: mixed}`
- **Always use response macros** for JSON API responses (never use `response()->json()` directly)
- Use `response()->success()` for successful operations
- Use `response()->error()` for error responses with appropriate HTTP status codes

### Cache Invalidation

**Rules:**
- Use cache versioning instead of manual cache clearing
- All user-related caches use versioned keys: `{key}_v{$cacheVersion}`
- Update `users.cache_version` timestamp to invalidate all related caches automatically
- No manual `Cache::forget()` calls needed for versioned keys
- Use controller traits for cache invalidation logic
- All content modification methods must call `invalidateUserCache()`

### Service Locator Anti-Pattern

**Rules:**
- **NEVER use `app(Service::class)`** - this is an anti-pattern
- **ALWAYS inject services via constructor promotion**
- Service locator pattern makes dependencies unclear and harder to test
- Violates SOLID principles and Laravel best practices

### Database Transactions

**Rules:**
- **Use database transactions for all multi-step operations** to ensure atomicity
- Wrap bulk operations in transactions (approve, reject, delete, update)
- Use transactions when creating/updating related records
- Transactions ensure data consistency if any step fails
- Use `\DB::transaction()` for transaction wrapping

### Security Best Practices

**Rules:**
- **Prevent User Enumeration**: Use generic error messages for authentication failures
- **Error Message Security**: Never reveal whether a user exists or which field is incorrect
- **Information Disclosure**: Log detailed errors server-side, but return generic messages to users
- **XSS Prevention**: Always escape output using `{{ }}` in Blade templates
- **CSRF Protection**: All POST/PUT/PATCH/DELETE requests must include CSRF tokens
  - Use `@csrf` directive in Blade forms
  - Use `makeRequest` utility for AJAX requests (automatically includes token)
  - Tokens are automatically refreshed on 419 errors
  - See [CSRF Token Guide](../docs/CSRF_TOKEN_GUIDE.md) for details
- **API Security**: All API endpoints must verify viewing sessions or authentication before returning data
- **Session Expiration**: Viewing sessions expire after configurable timeout (default: 24 hours)
- **Session Storage**: Viewing sessions stored in `device_registrations` table to persist across Laravel session regenerations
- **PIN Entry Flow**: PIN entry happens once from profile selection page (`/`), not from within gallery/player pages
- **Rate Limiting**: Implement rate limiting for authentication attempts
- **Token Security**: Device tokens are signed and expire after configurable TTL (default: 90 days)

---

## Browser Compatibility

### Supported Browsers

**Modern Browsers:**
- Chrome/Edge (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Mobile browsers (iOS Safari 12+, Chrome Mobile)

**Minimum Requirement:**
- **PS4 Internet Browser** (ES2020 support required)
- Modern browser APIs are natively supported
- No polyfills or fallbacks needed

### Modern JavaScript Features

**Available Features:**
- ES6 Modules (native `import`/`export`)
- Arrow functions
- Template literals
- Optional chaining (`?.`)
- Async/await
- Default parameters
- Spread operator
- `const`/`let`
- URL constructor
- TextEncoder
- String.padStart
- CSS custom properties
- CSS `gap` property
- Modern CSS transforms

**Rules:**
- **No polyfills required** - all features are natively supported
- **No compatibility fallbacks** - PS4 browser provides full ES2020 support
- Use modern JavaScript syntax throughout
- Use modern CSS features (custom properties, gap, transforms)
- Test on PS4 browser to ensure compatibility

---

## Code Quality

### Redundancy Removal

**Rules:**
- Remove unused functions/variables
- Consolidate duplicate logic
- Remove commented-out code
- Clean up legacy code paths
- Regular code reviews for dead code

### Performance

**Rules:**
- Review JavaScript bundle size regularly
- Ensure SCSS is properly minified
- Avoid unnecessary DOM queries (cache selectors)
- Optimize event listeners (use delegation when possible)
- Minimize reflows/repaints
- Use Vite's chunk splitting for better caching

### Error Handling

**Rules:**
- **Always use `ErrorHandler` utility** from `core/error-handler.js` for standardized error handling
- Implement proper error handling with consistent patterns
- Use try-catch for async operations
- Provide user-friendly error messages
- Log errors appropriately (with context, without sensitive data)
- Handle edge cases
- **Prevent Information Disclosure**: Use generic error messages for authentication failures
- **External API Calls**: Always wrap external API calls (YouTube, etc.) in try-catch blocks with timeout handling
- **Error Logging**: Log detailed errors server-side, but return generic messages to users
- **Network Errors**: Distinguish between network errors and API errors, provide appropriate fallback messages

---

## Documentation Requirements

### Commit & push workflow

Documentation is part of the deliverable. On every commit — and again before push — verify the diff does not leave docs stale. Full workflow, mapping table, and checklists: [Development → Documentation on commit & push](DEVELOPMENT.md#documentation-on-commit--push).

**Minimum before commit:**

- Behaviour, routes, or APIs changed → TECHNICAL_BRIEF and/or ARCHITECTURE
- New env keys → `.env.example` + DEVELOPMENT
- Schema/migrations → SCHEMA_NOTES
- New patterns or file layout → BEST_PRACTICES_RULEBOOK
- Include doc edits in the **same commit** as the code when possible

### JavaScript Documentation

**Rules:**
- Add JSDoc comments to all functions/classes
- Document parameters and return values
- Include examples for complex functions
- Document side effects

### Blade Component Documentation

**Rules:**
- Document all component props
- Use type hints in comments
- Describe component purpose
- Include usage examples
- Document any special requirements

### Inline Comments

**Rules:**
- Add comments for complex logic
- Explain "why" not "what"
- Keep comments up to date
- Remove obsolete comments
- Use clear, concise language
- Document compatibility fallbacks (STEP 1: Modern, STEP 2: Fallback)

---

## Quick Reference

### File Naming
- **JavaScript**: kebab-case (`video-player.js`, `pin-entry-modal.js`)
- **Blade**: kebab-case (`admin-password-modal.blade.php`)
- **SCSS**: kebab-case with underscore prefix (`_navbar.scss`)
- **PHP Classes**: PascalCase (`DeviceController.php`, `YouTubeService.php`)
- **Constants**: PascalCase in `app/Constants/` (`DeviceConstants.php`)

### Code Naming
- **Variables/Functions**: camelCase (`currentView`)
- **Classes**: PascalCase (`Gallery`)
- **Constants**: UPPER_SNAKE_CASE (`MAX_RETRIES`)
- **CSS Classes**: kebab-case (`view-gallery`)

### DOM Rules
- ✅ Toggle classes
- ❌ Create/remove elements
- ❌ Use `replaceWith()`
- ❌ Manipulate innerHTML (except documented exceptions)

### State Management
- Use `appState` as single source of truth
- Subscribe to changes, don't poll
- Use `eventEmitter` for cross-module communication

### Laravel 12 Features
- ✅ Constructor promotion for dependency injection
- ✅ Modern PHP 8.2+ syntax
- ✅ Typed properties
- ✅ Strict types on new/touched PHP files (`declare(strict_types=1)`)
- ✅ PHP 8.2+ features (`array_is_list()`, match expressions)
- ✅ View Composers (logic moved from Blade templates)
- ❌ Service locator pattern (`app(Service::class)`)

### SCSS Variables
- ✅ Use Bootstrap CSS variables (`var(--bs-*)`) when available
- ✅ Use direct `rgba()` values for opacity
- ✅ Only create custom variables for values not in Bootstrap
- ❌ Don't create variables for opacity values
- ❌ Don't create variables for Bootstrap-available colors

---

## Version History

- **Version 2.0** - Initial rulebook for Traktor v2 (Laravel 12)
  - Added constructor promotion patterns
  - Added DeviceTokenService documentation
  - Updated for Laravel 12 features
  - Maintained PS4 compatibility patterns
  - Updated Vite 5 configuration patterns
  - Enhanced device registration documentation
  - **Version 2.0.1** - Playlist state management refactoring
    - Added PlaylistStateManager module documentation
    - Added view-aware operations guidelines
    - Added view-helpers utility documentation
    - Updated event-driven architecture rules (prefer direct calls when appropriate)
    - Enhanced state management section with playlist-specific rules
  - **Version 2.1** - Complete Modernization (2025)
    - Dropped iOS 10.3.4 support, PS4 minimum requirement
    - Converted all JavaScript to native ES6 modules
    - Modernized JavaScript syntax (arrow functions, template literals, optional chaining)
    - Removed all polyfills (URLSearchParams, TextEncoder, padStart)
    - Modernized CSS (custom properties, gap property, transforms)
    - Updated service worker to async/await
    - Added View Composers (moved logic from Blade templates)
    - Added PHP 8.2+ features (strict types, array_is_list)
    - Standardized API responses with response macros
    - Updated all documentation to reflect modern patterns
  - **Version 2.2** - Player Structure Simplification (2025)
    - Simplified player to 4-layer structure (iframe, click blocker, overlay, controls)
    - Removed all JavaScript DOM creation - all HTML now in Blade templates
    - Simplified click blocker logic (direct CSS class checks, no property tracking)
    - Removed `createControls()` and `setupControlsOnExisting()` methods
    - Clear layer separation with `data-layer` attributes
    - Simplified event handling with single click blocker handler
  - **Version 2.3** - Locale Switching Simplification (2025)
    - Redesigned locale switching to use standard Laravel form POST → redirect pattern
    - Removed AJAX complexity from locale switching (simplified ~300 lines of JavaScript)
    - Locale switching now works without JavaScript (progressive enhancement)
    - Controller updates session and redirects, middleware applies locale on next request
    - Follows Laravel best practices: simple, reliable, maintainable
  - **Version 2.4** - Analytics Dashboard (2025)
    - Added comprehensive video watch analytics tracking system
    - Event-based tracking (started, paused, resumed, completed, abandoned, position updates)
    - **Event-based sessions**: Sessions derived server-side from events (grouped by 30-minute time gaps)
    - No explicit session management - simpler and more reliable than browser-dependent session tracking
    - Parent dashboard with Activity Overview and Content Insights tabs
    - 90-day data retention (configurable in future)
    - Admin user selection interface for viewing all users' analytics
    - Device name tracking in activity logs
    - Analytics tracking integrated into video player
    - Throttled position updates (15 seconds) for performance
    - Database-optimized queries with composite indexes
  - **Version 2.4.1** - Gallery Landscape Controls Cleanup (2025)
    - Cleaned up mobile landscape mode controls structure
    - Unified all landscape buttons (back, sidebar toggle, filter pills) in single container
    - Removed duplicate fixed positioning conflicts
    - Added unique IDs for landscape filter pills to avoid conflicts
    - Implemented event delegation for reliable back button handling
    - Consistent circular button styling (40px × 40px) for all landscape controls
    - Simplified CSS with relative positioning within container
  - **Version 2.5** - Documentation sync (2026)
    - Replaced `device-fingerprint.js` with `device-identity.js` in file trees
    - Documented gallery `index` as active entry; `show` as legacy
    - Added quota monitor, wake-lock, orientation modules
    - Added `QuotaController`, `SettingPolicy`, expanded services list
    - Added [CSRF Token Guide](CSRF_TOKEN_GUIDE.md); softened strict-types / no-IIFE rules to match codebase
    - Documented `routes/web.php` as source of truth for browser APIs
    - Added [commit & push documentation workflow](DEVELOPMENT.md#documentation-on-commit--push)

---

*This rulebook is based on the actual implementation of Traktor v2. For technical details about features and architecture, refer to the [Technical Brief](TECHNICAL_BRIEF.md). For CSRF token handling details, refer to the [CSRF Token Guide](CSRF_TOKEN_GUIDE.md).*


