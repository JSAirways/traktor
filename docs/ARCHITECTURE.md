# Architecture

## Two session layers

Traktor separates **parent/admin authentication** from **device trust** and **child viewing**.

| Layer | Mechanism | Purpose |
|-------|-----------|---------|
| Laravel auth session | Session cookie + `Auth` | Parent/admin `/admin` access |
| Device registration | HttpOnly cookies `device_token`, `device_uid`, `parent_user_id` | Trust this browser for a parent account |
| Viewing session | Fields on `device_registrations` (`current_viewing_slug`, `viewing_validated_at`, `viewing_expires_at`) | Which child profile is unlocked (PIN) |

Viewing state lives on the device row so regenerating the Laravel session (e.g. after admin login) does not wipe a child’s unlock. If no device cookie exists, `ViewingSessionService` can fall back to Laravel session keys.

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

### Server services

- `DeviceRegistrationService` — register/reactivate, cookie helpers, validate + grace refresh, discovery by uid
- `DeviceTokenService` — issue/decode HMAC tokens, normalize capabilities
- Config: `config/access.php` (`DEVICE_TOKEN_TTL`, `DEVICE_TOKEN_GRACE_MINUTES`, `DEVICE_COOKIE_EXPIRATION`)

### Privacy / Incognito

Incognito (and wiped console browser data) starts without cookies or storage — blank welcome is expected. Known accounts only appear when `device_uid` is still available from storage or cookie.

### Cutover note

Migration `2026_08_13_160000_add_device_uid_to_device_registrations_table` wipes device rows for a clean sheet. `2026_08_13_170000_drop_device_fingerprint_…` removes the retired fingerprint column.

## Request pipeline (web)

Notable middleware (`app/Http/Kernel.php`):

| Alias / stack | Role |
|---------------|------|
| `web` + `SetLocale` | Session, cookies, CSRF, locale |
| `account.approved` | Block unapproved parents from admin |
| `viewing.session` | Require valid viewing unlock for gallery/player |
| `rate.limit.pin` | Throttle PIN attempts |

CSRF exceptions (see `VerifyCsrfToken`): selected device/analytics/admin-password endpoints used by the frontend.

## Domain layout

Controllers stay thin; business logic lives under `app/Services/`:

| Area | Examples |
|------|----------|
| Device / viewing | `DeviceRegistrationService`, `DeviceTokenService`, `ViewingSessionService`, `PinService` |
| Content | `ContentService`, `YouTubeService` |
| Users | `UserApprovalService`, `AuthenticationService`, `UserLookupService` |
| Analytics | `AnalyticsService` |
| Ops | `GoogleCloudMonitoringService`, profile pictures, cache invalidation |

Policies: `UserPolicy`, `ContentPolicy`. Models under `app/Models/`.

## Content and YouTube

- Content is scoped to a **profile user** (`videos.user_id` / playlists) — parent or child
- Imports may run inline or via `ImportVideoJob` / `ImportPlaylistJob`
- API key from `settings` table (`YouTubeService`), not `.env`
- Optional Google Cloud Monitoring credentials (settings) power quota UI

## Frontend

- Vite 5 entrypoints in `vite.config.js` (app shell, welcome, gallery, player, admin modules)
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
| `/api/device/*`, `/api/analytics/*`, `/api/view/*` | Device, analytics, PIN APIs |

Some `/api/*` endpoints are registered in both `routes/web.php` and `routes/api.php` for session/CSRF compatibility — prefer the web-mounted variants for browser clients.
