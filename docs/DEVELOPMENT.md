# Development

## Stack

- PHP 8.2+, Laravel 12
- MySQL or PostgreSQL (MySQL in `.env.example`)
- Node 18+, Vite 5, Bootstrap 5, vanilla ES modules
- YouTube Data API v3 (key in Admin → Settings)

## First-time setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# Edit .env — especially DB_* and APP_URL
php artisan migrate
npm run build
php artisan serve
```

Asset HMR (separate terminal):

```bash
npm run dev
```

Convenience scripts:

| Command | What it does |
|---------|----------------|
| `composer setup` | install, env, key, migrate --force, npm install/build |
| `composer dev` | concurrently: `serve` + `queue:listen` + `pail` + `vite` |
| `composer test` / `php artisan test` | PHPUnit |

After first admin login, set the YouTube API key at **Admin → Settings** (`/admin/settings`). Imports will not work without it.

### First admin user

There is no automatic seeder for an admin. For local testing, create/promote a user via tinker after registering and approving an account, for example:

```bash
php artisan tinker
>>> $u = \App\Models\User::where('email', 'you@example.com')->first();
>>> $u->forceFill(['role' => 'admin', 'account_status' => 'approved'])->save();
```

See [Schema notes](SCHEMA_NOTES.md) for more bootstrap tips.

## Environment variables

App-specific device and access settings (`config/access.php`):

| Variable | Default (minutes unless noted) | Purpose |
|----------|--------------------------------|---------|
| `DEVICE_TOKEN_TTL` | 129600 (90 days) | Signed device token lifetime |
| `DEVICE_TOKEN_GRACE_MINUTES` | 129600 (90 days) | Allow refresh after expiry within this window |
| `DEVICE_COOKIE_EXPIRATION` | 259200 (180 days) | Cookie max-age; keep ≥ TTL + grace |
| `PIN_LENGTH` | 4 | Child view PIN length |
| `VIEWING_SESSION_TIMEOUT` | 86400 **seconds** | Viewing unlock lifetime |
| `PIN_RATE_LIMIT_ATTEMPTS` / `PIN_RATE_LIMIT_WINDOW` | 5 / 15 | PIN brute-force throttle |

Also required in practice: `APP_KEY`, `DB_*`, `MAIL_*` for notifications, `SESSION_*`.

**Not in `.env`:** YouTube API key and optional Google Cloud quota credentials — Admin → Settings → `settings` table.

Full template: `.env.example`.

## Frontend workflow

- Entry points: `vite.config.js` (`resources/js/app.js`, welcome, gallery, player, admin modules)
- Device identity: `resources/js/core/device-identity.js`
- After JS/CSS changes: `npm run build` (or leave `npm run dev` running)
- Clear compiled views when Blade caches confuse you: `php artisan view:clear`

## Queue

Import jobs use the queue. `.env.example` defaults to `QUEUE_CONNECTION=sync` (jobs run inline). For async imports:

```bash
php artisan queue:listen
# or
composer dev
```

## i18n

- Strings: `lang/en/*.php`, `lang/de/*.php`
- Add keys to both locales when changing UI copy
- Switcher: `POST /locale/switch`

## Testing checklist (device identity)

Useful after device-related changes:

1. Register a device → reload → still on profile selection (cookie trust)
2. Device logout → welcome may list known accounts if `device_uid` remains in storage
3. Password login again → same `device_uid` row reactivated (no duplicate for that parent)
4. Clear site data → blank welcome; new registration creates a new device row
5. Incognito → blank discovery (partitioned storage)
6. Second parent on same browser → second row, shared `device_uid`

## Conventions

- Prefer services under `app/Services/` over fat controllers
- Do not commit `.env` or secrets
- Device identity must remain PS4-safe: no `crypto.randomUUID()` / no reliance on `crypto.subtle` for identity
- Migrations that wipe data must be called out in comments and in docs (see device_uid cutover)

## Related docs

- [Technical brief](TECHNICAL_BRIEF.md)
- [Architecture](ARCHITECTURE.md)
- [Schema notes](SCHEMA_NOTES.md)
- Root [README](../README.md) — quick start and YouTube key setup
