# Architecture

## Two session layers

Traktor separates **parent/admin authentication** from **device trust** and **child viewing**.

| Layer | Mechanism | Purpose |
|-------|-----------|---------|
| Laravel auth session | Session cookie + `Auth` | Parent/admin `/admin` access |
| Device registration | HttpOnly cookies `device_token`, `device_uid`, `parent_user_id` | Trust this browser for a parent account |
| Viewing session | Fields on `device_registrations` (`current_viewing_slug`, `viewing_validated_at`, `viewing_expires_at`) | Which child profile is unlocked (PIN) |

Viewing state lives on the device row so regenerating the Laravel session (e.g. after admin login) does not wipe a child’s unlock. If no device cookie exists, `ViewingSessionService` can fall back to Laravel session keys.

Parent accounts may also store an **`admin_pin`** on `users`. This is separate from child/profile `view_pin` and is only used by the registered-device admin access modal as a convenience alternative to the account password.

## Device identity model

Fingerprint-based identity was retired. Current model:

```text
Client                         Server
──────                         ──────
device_uid (UUID)
  localStorage / sessionStorage
  form field on register/login
        │
        ▼
  POST register-device  ──────►  resolveDeviceUidFromRequest()
                                prefer cookie uid over body
                                unique (parent_user_id, device_uid)
                                issue HMAC device_token
        ◄────────────────────  Set-Cookie: device_token, device_uid, parent_user_id
```

| Signal | Role |
|--------|------|
| `device_uid` | Stable identity + known-account discovery after logout |
| `device_token` | Trust boundary for profile selection / viewing storage |
| Capabilities JSON | Metadata / admin badges; capability hash can trigger token reissue |

### Client helpers

- `resources/js/core/device-identity.js` — UUID (PS4-safe), storage, form fill, browser/capability collection; global `Traktor.Core.deviceIdentity`
- `resources/js/core/device-api.js` — registered users + capability refresh APIs
- `resources/js/core/pin-input.js` — shared 4-digit modal PIN input behavior for viewing PIN and admin PIN flows

### Server services

- `DeviceRegistrationService` — register/reactivate, cookie helpers, validate + grace refresh, discovery by uid
- `DeviceTokenService` — issue/decode HMAC tokens, normalize capabilities
- Config: `config/access.php` (`DEVICE_TOKEN_TTL`, `DEVICE_TOKEN_GRACE_MINUTES`, `DEVICE_COOKIE_EXPIRATION`)

### Privacy / Incognito

Incognito (and wiped console browser data) starts without cookies or storage — blank welcome is expected. Known accounts only appear when `device_uid` is still available from storage or cookie.

### Cutover note

Migration `2026_08_13_160000_add_device_uid_to_device_registrations_table` wipes device rows for a clean sheet. `2026_08_13_170000_drop_device_fingerprint_…` removes the retired fingerprint column.

## Request pipeline (web)

Routing is loaded from `bootstrap/app.php`: `routes/web.php` and `routes/admin.php` (both use the `web` middleware group). Middleware aliases are registered in `bootstrap/app.php`; the `web` group (including `SetLocale`) is defined in `app/Http/Kernel.php`.

| Alias / stack | Role |
|---------------|------|
| `web` + `SetLocale` | Session, cookies, CSRF (`App\Http\Middleware\VerifyCsrfToken` via `bootstrap/app.php`), locale |
| `account.approved` | Block unapproved parents from admin |
| `viewing.session` | Require valid viewing unlock for gallery/player |
| `rate.limit.pin` | Throttle attempts (scoped: `view`, `admin-password`) |

`POST /admin/verify-password` accepts either a password or a 4-digit admin PIN. Admin PIN is not rate-limited; password fallback uses `admin_password_attempts_*`. Viewing PIN uses `view_pin_attempts_*`. See [CSRF and AJAX](#csrf-and-ajax) below for exclusions and client helpers.

## CSRF and AJAX

Traktor uses Laravel CSRF for state-changing web requests. Browser clients should use the shared `makeRequest` helper rather than hand-rolling tokens.

### Server-side

- Middleware: `App\Http\Middleware\VerifyCsrfToken` (registered in `bootstrap/app.php` via `$middleware->web(replace: …)` — Laravel 12’s default `ValidateCsrfToken` has an empty except list, so the app class must replace it or exclusions never apply).
- All `POST` / `PUT` / `PATCH` / `DELETE` requests require a valid token unless explicitly excluded.
- Blade forms: `@csrf` or `<meta name="csrf-token" content="{{ csrf_token() }}">` in layouts.

### Excluded routes

These paths skip CSRF verification because they use other security measures (device cookies, rate limiting, viewing-session checks). Canonical list: `$except` in `VerifyCsrfToken`.

| Path | Rationale |
|------|-----------|
| `admin/verify-password` | Device registration + password + throttle |
| `device/logout` | Device cookie; clears trust state |
| `api/analytics/*` | Viewing-session validation; long-lived player sessions |

### Token refresh

`GET /csrf-token` returns `{ "token": "..." }` for stale tokens on cached or long-lived PWA pages.

### Client-side

Use `makeRequest` from `resources/js/core/utils.js`:

1. Reads the token from `<meta name="csrf-token">` or `input[name="_token"]`.
2. Sends `X-CSRF-TOKEN` on mutating requests.
3. On **419**, calls `/csrf-token`, updates the DOM, and retries once.

Pass `skipCsrf: true` only for routes in `VerifyCsrfToken::$except`. Helpers: `getCsrfToken()`, `refreshCsrfToken()`, `updateCsrfToken(token)`.

Common mistakes: manual `X-CSRF-TOKEN` headers; assuming analytics endpoints need CSRF; ignoring stale tokens on long-lived PWA pages (rely on 419 refresh or call `refreshCsrfToken()` after returning from background).

## Domain layout

Controllers stay thin; business logic lives under `app/Services/`:

| Area | Examples |
|------|----------|
| Device / viewing | `DeviceRegistrationService`, `DeviceTokenService`, `ViewingSessionService`, `PinService` |
| Content | `ContentService`, `YouTubeService`, `AssetService` |
| Users | `UserApprovalService`, `AuthenticationService`, `UserLookupService`, `ProfilePictureService` |
| Analytics | `AnalyticsService` |
| Ops | `GoogleCloudMonitoringService` (YouTube quota UI), cache invalidation via `users.cache_version` |

View composers under `app/View/Composers/` (`AppComposer`, `DeviceComposer`, `GalleryComposer`, `PlayerComposer`, etc.) inject shared layout data. `DeviceComposer` also exposes whether the registered parent has an `admin_pin`, so the frontend can default the admin access modal to PIN entry.

Policies: `UserPolicy`, `ContentPolicy`, `SettingPolicy`. Models under `app/Models/`.

## Content and YouTube

- Content is scoped to a **profile user** (`videos.user_id` / playlists) — parent or child
- Imports may run inline or via `ImportVideoJob` / `ImportPlaylistJob`
- API key from `settings` table (`YouTubeService`), not `.env`
- Optional Google Cloud Monitoring credentials (settings) power quota UI at **Admin → Settings** (`QuotaController`, `/admin/quota/stats`)

## Frontend

- Vite 5 entrypoints in `vite.config.js` (app shell, welcome, gallery, player, admin modules — see [Development](DEVELOPMENT.md))
- Gallery route `/{slug}/gallery` renders `galleries/index.blade.php` with `resources/js/resources/galleries/index.js`
- Bootstrap 5 + vanilla ES modules
- PWA: `public/site.webmanifest`, `public/sw.js`, `resources/js/core/pwa-installer.js`

## i18n

- `lang/en`, `lang/de`
- `LocaleController` + `SetLocale`; preference on `users.locale` when authenticated

## Key routes (orientation)

| Path | Role |
|------|------|
| `/`, `/welcome` | Profile selection / device onboarding |
| `/register-device` | Device registration |
| `/{slug}/gallery`, `/{slug}/player/...` | Kid viewing |
| `/admin/...` | Parent/admin console |
| `/admin/admin/devices` | Global device management (admins) |
| `/admin/quota/stats` | YouTube quota stats (admin) |
| `/api/device/*`, `/api/analytics/*`, `/api/view/*` | Device, analytics, PIN APIs |

### API routing note

Browser-facing `/api/*` routes are registered in **`routes/web.php`** so they share the session/CSRF stack. The file `routes/api.php` still exists (legacy / reference) but is **not loaded** — `RouteServiceProvider` is not registered in `bootstrap/providers.php`. Don’t add new browser API routes there; use `web.php`.

Analytics: `POST /api/analytics/track` is the active endpoint. `POST /api/analytics/session/start` and `/end` remain as no-op stubs for backward compatibility — sessions are derived server-side from events.

## Related docs

- [Technical brief](TECHNICAL_BRIEF.md)
- [Development](DEVELOPMENT.md)
- [Schema notes](SCHEMA_NOTES.md)
- [Conventions](CONVENTIONS.md)
