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

## Feature map

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

## Core capabilities (summary)

- **Access** — Public parent registration with approval; session-based parent/admin login at `/admin`; password reset and email verification.
- **Devices** — Durable `device_uid` (cookie + storage) and HMAC-signed `device_token`; multi-parent on one browser; optional admin-access PIN on the registered-device modal (separate from child viewing PIN).
- **Kid viewing** — Profile selection on `/`; optional viewing PIN; gallery/player under `/{slug}/…`; PWA install + service worker.
- **Content** — YouTube Data API v3 imports (key in Admin → Settings / `settings` table, not `.env`); per-profile library, visibility, ordering, channel layout; optional queue-backed import jobs.
- **YouTube “sign in to confirm”** — Parent completes YouTube’s own sign-in under **Admin → Settings** in the **same browser**; Traktor does not store that session.
- **Analytics** — Event-based watch tracking; sessions derived server-side; dashboard aggregates for parents (admins can scope more broadly).
- **i18n** — English and German (`lang/en`, `lang/de`).
- **Quota UI** — Optional Google Cloud credentials in Admin → Settings for `/admin/quota/stats`.

Viewing PIN attempts are rate-limited; admin PIN on the verify-password endpoint is not. Details: [Architecture](ARCHITECTURE.md).

## Out of scope / not live

- **Parental controls JSON / `ParentalControlService`** — model fields exist; not enforced on gallery/player paths yet. Not a shipped feature.
- **Alpine.js** — listed in `package.json` but the app UI uses vanilla ES modules.
- **Legacy files** — `galleries/show.*`, standalone `pins/entry` route/view, and unloaded `routes/api.php` player fragments remain in the tree but are not part of the active flow.

## Related docs

- [Architecture](ARCHITECTURE.md) — device vs auth sessions, CSRF, token model
- [Development](DEVELOPMENT.md) — setup and env
- [Schema notes](SCHEMA_NOTES.md) — tables and local admin bootstrap
- [Conventions](CONVENTIONS.md) — coding rules and gotchas
