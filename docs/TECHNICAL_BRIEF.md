# Technical brief

## Purpose

Traktor is a curated YouTube gallery for families. Parents import and organize videos, playlists, and channels for each child profile. Children browse a simple, kid-friendly gallery on registered devices (desktop browsers, tablets, TVs, consoles such as PS4) after selecting a profile and unlocking with a PIN when configured.

## Roles

| Role | Identification | Capabilities |
|------|----------------|--------------|
| **Admin** | `users.role = admin` | Approve/reject registrations, manage all users and devices, system settings (YouTube API key, quota monitoring), global analytics |
| **Parent** | `role = user`, `parent_id` null | After approval: register devices, manage children and PINs, curate content per profile, manage own devices and child visibility, view analytics |
| **Child** | `parent_id` set | No normal login. Appear on device profile selection; browse gallery/player via device trust + viewing session |
| **Guest** | No valid device cookie | Welcome screen: register account, register device with parent credentials, password recovery |

Account lifecycle statuses: `pending` → `approved` / `rejected`.

## Core capabilities

### Account and access

- Public parent registration with optional “how heard about us” and approval workflow
- Email notifications for registration / approval flows
- Session-based parent/admin login to `/admin`
- Password reset and email verification routes (Laravel Breeze-style)

### Device identity and registration

- Durable **`device_uid`** (UUID) identifies a browser/profile across re-logins
- Stored in HttpOnly cookie `device_uid` plus best-effort `localStorage` / `sessionStorage` (`traktor_device_uid`)
- HMAC-signed **`device_token`** cookie unlocks profile selection and viewing (not Laravel Auth)
- Token TTL + grace refresh so trust survives expiry without spawning duplicate devices
- Capability snapshot (touch, screen, storage, etc.) stored on the device row for admin display
- Multi-parent: same physical browser can register under different parent accounts (same `device_uid`, different `parent_user_id`)
- Parent and admin UIs to list, rename, logout, delete devices and control which children are visible per device
- Optional **admin access PIN** on parent profiles for opening the backend from a registered device; device modal can fall back to password

### Kid viewing experience

- Home `/` shows profile selection when a device is registered
- Optional PIN per profile (modal on profile selection); viewing session stored on the device row (survives admin login session regenerate)
- Gallery and player under `/{slug}/gallery` and `/{slug}/player/...` (gallery uses unified `galleries/index` view + JS entry)
- Progressive Web App: installable, service worker for selected static/API caching

### Content curation

- Import videos, playlists, and channels via YouTube Data API v3
- Per-profile content library with visibility, ordering, bulk actions, channel sidebar layout
- Channel import UI and background import jobs when queue is configured
- YouTube API key configured in **Admin → Settings** (database `settings` table, not `.env`)

### Analytics

- Event-based watch tracking from the player (`started`, `paused`, `resumed`, `completed`, `abandoned`, `position_update`)
- Sessions derived server-side from events (30-minute gaps); legacy `session/start` and `session/end` API stubs are no-ops
- Admin dashboard aggregates watch time and usage for parent/children (admins can scope more broadly)

### Security notes

- The **viewing PIN** and **admin access PIN** are separate values with separate purposes.
- Admin PIN is a **4-digit, device-bound convenience check** for opening the backend from the registered-device UI. It does not replace the normal account password or `/login`.
- Admin PIN attempts use scoped rate-limit buckets separate from profile viewing PIN (`admin_pin_attempts_*` vs `view_pin_attempts_*`). Password fallback on the same endpoint uses `admin_password_attempts_*`. Configure limits via `PIN_RATE_LIMIT_ATTEMPTS` and `PIN_RATE_LIMIT_WINDOW`.

### YouTube quota monitoring

- Optional Google Cloud credentials in **Admin → Settings**
- Quota stats API (`/admin/quota/stats`) and settings UI (`quota-monitor.js`) for daily usage visibility

### Internationalization

- Locales: English and German (`lang/en`, `lang/de`)
- Locale switcher persists to session and authenticated user preference

## Feature map (high level)

```text
Welcome (/welcome)
  ├─ Register account → pending approval
  ├─ Register / password-login device → device cookies → Home (/)
  └─ Admin password (on registered device) → /admin

Home (/) — profile tiles
  └─ PIN modal (optional) → Viewing session
        ├─ Gallery /{slug}/gallery
        └─ Player /{slug}/player/...

Admin (/admin) — parents & admins
  ├─ Dashboard / analytics
  ├─ Children + PINs
  ├─ Content + YouTube import
  ├─ Devices (+ visibility)
  ├─ Users / approval (admin)
  └─ Settings (YouTube key, mail, quota)
```

## Out of scope / not live

- **Parental controls JSON / `ParentalControlService`** — model fields exist; not enforced on gallery/player paths yet. Do not treat as a shipped product feature.
- **Alpine.js** — listed in `package.json` but the app UI uses vanilla ES modules.
- **Legacy files** — `galleries/show.*`, standalone `pins/entry` route/view, and unloaded `routes/api.php` player fragments remain in the tree but are not part of the active flow.

## Related docs

- [Architecture](ARCHITECTURE.md) — device vs auth sessions, token model
- [Development](DEVELOPMENT.md) — setup and env
- [Schema notes](SCHEMA_NOTES.md) — tables and local admin bootstrap
