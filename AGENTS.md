# Agents

Traktor is a Laravel 12 family YouTube gallery: parents curate content per child profile; kids browse on registered devices after optional PIN unlock.

## Read order

1. [docs/TECHNICAL_BRIEF.md](docs/TECHNICAL_BRIEF.md) — product model, roles, what is not live
2. [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — auth vs device vs viewing sessions, CSRF/AJAX, routing
3. [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) — setup, env, Vite entries, device test checklist
4. [docs/SCHEMA_NOTES.md](docs/SCHEMA_NOTES.md) — entities and local bootstrap
5. [docs/CONVENTIONS.md](docs/CONVENTIONS.md) — project-specific coding rules

Prefer the repository tree and source for file lists. Docs are for invariants and gotchas.

## Things not to break

- **Three layers:** Laravel auth (parent/admin `/admin`), device trust (`device_uid` / `device_token` cookies), viewing session (fields on `device_registrations`).
- **Browser `/api/*` routes** live in `routes/web.php`. Don’t add new browser API routes to `routes/api.php` (not loaded).
- **Device identity** needs to stay PS4-safe — use `resources/js/core/device-identity.js`; no `crypto.randomUUID()` for UIDs.
- **Mutating AJAX** uses `makeRequest` from `resources/js/core/utils.js`. CSRF details: [Architecture — CSRF and AJAX](docs/ARCHITECTURE.md#csrf-and-ajax).
- **No secrets in git or docs** — `.env` and real API keys stay out of the repo; YouTube/GCP credentials live in Admin → Settings.
- **i18n** — touch `lang/en` and `lang/de` together when changing UI copy.
- **Docs** — if behaviour or setup changes, update the matching doc when you can ([Keeping docs in sync](docs/DEVELOPMENT.md#keeping-docs-in-sync)).

## Security reports

Prefer private reporting over public issues for exploitable bugs. See [SECURITY.md](SECURITY.md).
