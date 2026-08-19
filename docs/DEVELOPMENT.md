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

After JS/CSS changes: `npm run build` (or leave `npm run dev` running)

Clear compiled views when Blade caches confuse you: `php artisan view:clear`

CSRF in AJAX: use `makeRequest` from `core/utils.js` — see [CSRF token guide](CSRF_TOKEN_GUIDE.md).

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
- Do not commit `.env` or secrets (`.env.example` is the committed template)
- Device identity must remain PS4-safe: no `crypto.randomUUID()` / no reliance on `crypto.subtle` for identity
- Migrations that wipe data must be called out in comments and in docs (see device_uid cutover)
- New browser `/api/*` routes belong in `routes/web.php`, not `routes/api.php` (see [Architecture](ARCHITECTURE.md))
- **Keep docs in sync when committing** — see [Documentation on commit & push](#documentation-on-commit--push) below

## Documentation on commit & push

Treat documentation as part of the change, not a follow-up task. Before every **commit** (and again before **push** if you amended or added commits locally), check whether the code diff requires a doc update and include those edits in the same commit or push.

### When docs must be updated

| You changed… | Update… |
|--------------|---------|
| Product behaviour, roles, user-facing flows | [TECHNICAL_BRIEF.md](TECHNICAL_BRIEF.md) |
| Sessions, device identity, middleware, routes, services | [ARCHITECTURE.md](ARCHITECTURE.md) |
| Setup, env vars, Vite entries, local workflows | [DEVELOPMENT.md](DEVELOPMENT.md) and [`.env.example`](../.env.example) |
| Database columns, migrations, bootstrap steps | [SCHEMA_NOTES.md](SCHEMA_NOTES.md) |
| Coding patterns, file layout, conventions | [BEST_PRACTICES_RULEBOOK.md](BEST_PRACTICES_RULEBOOK.md) |
| CSRF exceptions or AJAX token handling | [CSRF_TOKEN_GUIDE.md](CSRF_TOKEN_GUIDE.md) |
| High-level project overview or quick start | Root [README.md](../README.md) |
| New doc file or doc index entry | [docs/README.md](README.md) |

Skip doc updates only for changes that cannot affect behaviour or developer setup (e.g. typo in a comment, pure test assertion tweak with no API change).

### Pre-commit checklist

1. **Scan the diff** — routes, env/config keys, migrations, public APIs, admin UI flows, and file moves are the usual triggers.
2. **Open the mapped doc(s)** from the table above and fix anything now wrong or missing.
3. **Env vars** — new or renamed `env()` keys go in `.env.example` with a short comment; document defaults in DEVELOPMENT if non-obvious.
4. **Destructive migrations** — note them in SCHEMA_NOTES and ARCHITECTURE (same as device_uid cutover).
5. **Rulebook file trees** — if you add/remove/rename files under `resources/js/`, `resources/views/`, or `app/Services/`, update the trees in BEST_PRACTICES_RULEBOOK when the layout is meant to be canonical.
6. **Same commit** — stage doc changes together with code (`git add docs/ .env.example README.md` as needed).

### Pre-push checklist

1. **Re-read commits since last push** — if any commit lacks doc updates that the diff implies, amend or add a docs commit before pushing.
2. **Links and paths** — quick pass that new doc links resolve and file paths match the repo.
3. **No secrets in docs** — examples use placeholders; never copy values from `.env`.

### Suggested git flow

```bash
# After implementing a feature or fix
git diff                    # identify doc impact
# edit docs / .env.example as needed
git add -A                  # or add code + docs explicitly
git commit -m "feat: …"     # message can mention doc updates when substantial

# Before push
git log origin/main..HEAD   # review unpushed commits for missing doc coverage
git push
```

For large features, update docs incrementally in the same branch so the PR/commit history stays reviewable.

## Related docs

- [Technical brief](TECHNICAL_BRIEF.md)
- [Architecture](ARCHITECTURE.md)
- [Schema notes](SCHEMA_NOTES.md)
- [CSRF token guide](CSRF_TOKEN_GUIDE.md)
- Root [README](../README.md) — quick start and YouTube key setup
