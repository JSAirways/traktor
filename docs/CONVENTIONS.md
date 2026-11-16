# Conventions

Project-specific habits for Traktor development. Prefer the repo tree and code for file lists; use this doc for gotchas. Product behaviour: [Technical brief](TECHNICAL_BRIEF.md). System model: [Architecture](ARCHITECTURE.md).

## JavaScript layout

| Area | Role |
|------|------|
| `resources/js/core/` | Shared foundational helpers (utils, device identity, PWA, analytics, etc.) |
| `resources/js/modules/` | Reusable stateful UI (player, gallery, playlist, controls) |
| `resources/js/resources/` | Page/resource entry scripts (welcome, galleries, player, pins, accounts) |
| `resources/js/admin/` | Admin dashboard, content, settings, users |
| `resources/js/app.js` | Shell init and high-level coordination only |

- Use **kebab-case** filenames and native ES `import`/`export` for new and touched modules.
- Avoid new IIFE wrappers or `window.Traktor` assignments. Legacy global attachments remain in some modules during transition (`namespace.js`, `device-identity.js`, etc.).
- Relative import paths (e.g. `../../core/utils.js`).
- Target **PS4 browser** (ES2020). Device UID generation needs to stay PS4-safe: use `resources/js/core/device-identity.js` — don’t use `crypto.randomUUID()` or rely on `crypto.subtle` for identity.
- Legacy / unused: `galleries/show.js` exists but the gallery route uses `galleries/index.js`.

## Blade and UI

- Render UI in Blade; JavaScript toggles visibility/classes rather than building HTML.
- Escape output with `{{ }}`; use `{!! !!}` only when intentionally rendering trusted HTML.
- Forms: `@csrf` or the layout `<meta name="csrf-token">`.

## AJAX and CSRF

- Use `makeRequest` from `resources/js/core/utils.js` for mutating requests — it sends `X-CSRF-TOKEN` and retries once on 419 after refreshing the token.
- Pass `skipCsrf: true` only for routes listed in `VerifyCsrfToken::$except`.
- Details: [Architecture — CSRF and AJAX](ARCHITECTURE.md#csrf-and-ajax).

## Laravel backend

- Keep controllers thin; put business logic in `app/Services/`.
- Validate with Form Requests; authorize with policies (`UserPolicy`, `ContentPolicy`, `SettingPolicy`).
- Browser-facing `/api/*` routes belong in **`routes/web.php`** (session/CSRF stack). Don’t add new browser API routes to `routes/api.php` — that file is not loaded.
- Prefer constructor property promotion and typed properties (Laravel 12 / PHP 8.2+).

## i18n

- UI strings live in `lang/en/` and `lang/de/`. When changing copy, update **both** locales.

## Secrets and docs

- Don’t commit `.env` or real secrets. `.env.example` is the committed template.
- Doc examples use placeholders only.
- When behaviour, setup, or conventions change, update the matching doc when you can — see [Keeping docs in sync](DEVELOPMENT.md#keeping-docs-in-sync).

## Related docs

- [Technical brief](TECHNICAL_BRIEF.md)
- [Architecture](ARCHITECTURE.md)
- [Development](DEVELOPMENT.md)
- [Schema notes](SCHEMA_NOTES.md)
