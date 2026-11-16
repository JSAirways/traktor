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

Full local stack with Vite HMR (one terminal):

```bash
npm run dev
```

Opens Laravel at **http://127.0.0.1:8000** and Vite at **http://127.0.0.1:5173**.

Vite only (if the PHP app is already running elsewhere):

```bash
npm run vite
```

Convenience scripts:

| Command | What it does |
|---------|----------------|
| `composer setup` | install, env, key, migrate --force, npm install/build |
| `composer dev` | concurrently: `serve` + `queue:listen` + `pail` + `vite` |
| `composer test` / `php artisan test` | PHPUnit |

**Important:** Feature tests use `RefreshDatabase`, which rebuilds the database. PHPUnit is configured to use a separate database (`traktor_v2_test` in `phpunit.xml`), not your main `DB_DATABASE`. Create that empty database once on your MySQL server before running tests. Never point tests at production or shared dev data.

```bash
# One-time on MySQL (adjust credentials/host as needed)
CREATE DATABASE traktor_v2_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

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

Full template: [`.env.example`](../.env.example) in the repo root.

## Frontend workflow

Vite entry points (see `vite.config.js`):

| Entry | Purpose |
|-------|---------|
| `resources/css/app.scss` | Global styles |
| `resources/js/app.js` | App shell + core/modules bundle |
| `resources/js/resources/welcome/index.js` | Welcome / device onboarding |
| `resources/js/resources/galleries/index.js` | Gallery page (`/{slug}/gallery`) |
| `resources/js/resources/player/show.js` | Player pages |
| `resources/js/resources/pins/entry.js` | PIN modal (loaded on profile selection) |
| `resources/js/resources/accounts/forgot-password.js` | Password recovery |
| `resources/js/resources/shared/*` | Shared frontend widgets |
| `resources/js/admin/**` | Admin dashboard, content, settings/quota, users |

Device identity: `resources/js/core/device-identity.js`

After JS/CSS changes: `npm run build` (or leave `npm run dev` running).

Clear compiled views when Blade caches confuse you: `php artisan view:clear`.

CSRF in AJAX: use `makeRequest` from `core/utils.js` — see [Architecture — CSRF and AJAX](ARCHITECTURE.md#csrf-and-ajax).

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

## Conventions (pointers)

Full rules: [Conventions](CONVENTIONS.md). Highlights:

- Prefer services under `app/Services/` over fat controllers
- Don’t commit `.env` or secrets (`.env.example` is the committed template)
- Device identity needs to stay PS4-safe: no `crypto.randomUUID()` / no reliance on `crypto.subtle` for identity
- Migrations that wipe data should be called out in comments and in docs (see device_uid cutover)
- New browser `/api/*` routes belong in `routes/web.php`, not `routes/api.php` (see [Architecture](ARCHITECTURE.md))

## Keeping docs in sync

If a change affects behaviour, setup, or conventions, update the matching doc in the same commit when you can.

| You changed… | Update… |
|--------------|---------|
| Product behaviour, roles, user-facing flows | [TECHNICAL_BRIEF.md](TECHNICAL_BRIEF.md) |
| Sessions, device identity, middleware, routes, services, CSRF/AJAX | [ARCHITECTURE.md](ARCHITECTURE.md) |
| Setup, env vars, Vite entries, local workflows | [DEVELOPMENT.md](DEVELOPMENT.md) and [`.env.example`](../.env.example) |
| Database columns, migrations, bootstrap steps | [SCHEMA_NOTES.md](SCHEMA_NOTES.md) |
| Coding patterns or project rules | [CONVENTIONS.md](CONVENTIONS.md) |
| High-level project overview or quick start | Root [README.md](../README.md) |
| Agent/contributor entrypoints | [AGENTS.md](../AGENTS.md), [CONTRIBUTING.md](../CONTRIBUTING.md) |
| New doc file or doc index entry | [docs/README.md](README.md) |

Skip doc updates for changes that don’t affect behaviour or setup (e.g. a comment typo, a pure test assertion tweak). New or renamed `env()` keys belong in `.env.example`; destructive migrations deserve a note in SCHEMA_NOTES and ARCHITECTURE. Don’t copy secrets from `.env` into docs.

## Related docs

- [Technical brief](TECHNICAL_BRIEF.md)
- [Architecture](ARCHITECTURE.md)
- [Schema notes](SCHEMA_NOTES.md)
- [Conventions](CONVENTIONS.md)
- Root [README](../README.md) — quick start and YouTube key setup
