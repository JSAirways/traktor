# CSRF token guide

Traktor uses Laravel's CSRF protection for state-changing web requests. Browser clients should use the shared `makeRequest` helper rather than hand-rolling tokens.

## Server-side

- Middleware: `App\Http\Middleware\VerifyCsrfToken`
- All `POST` / `PUT` / `PATCH` / `DELETE` requests require a valid token unless explicitly excluded.
- Blade forms: use `@csrf` or `<meta name="csrf-token" content="{{ csrf_token() }}">` in layouts.

### Excluded routes

These paths skip CSRF verification because they use other security measures (device cookies, rate limiting, viewing-session checks):

| Path | Rationale |
|------|-----------|
| `admin/verify-password` | Device registration + password + throttle |
| `device/logout` | Device cookie; clears trust state |
| `api/analytics/*` | Viewing-session validation; long-lived player sessions |

See `$except` in `VerifyCsrfToken` for the canonical list.

### Token refresh endpoint

```
GET /csrf-token
```

Returns `{ "token": "..." }`. Used by the frontend when a cached page has a stale token.

## Client-side

Use `makeRequest` from `resources/js/core/utils.js`:

```javascript
import { makeRequest } from '../../core/utils.js';

const response = await makeRequest('/api/view/validate-pin', {
    method: 'POST',
    body: { slug, pin },
});
```

### Behaviour

1. Reads the token from `<meta name="csrf-token">` or `input[name="_token"]`.
2. Sends `X-CSRF-TOKEN` on mutating requests.
3. On **419** (token mismatch/expired), calls `/csrf-token`, updates the DOM, and retries once.

### Excluded routes from JavaScript

Pass `skipCsrf: true` only for routes listed in `VerifyCsrfToken::$except`:

```javascript
await makeRequest('/admin/verify-password', {
    method: 'POST',
    body: formData,
    skipCsrf: true,
});
```

### Utilities

| Function | Purpose |
|----------|---------|
| `getCsrfToken()` | Read current token from DOM |
| `refreshCsrfToken()` | Fetch and apply a new token from `/csrf-token` |
| `updateCsrfToken(token)` | Apply a token returned inline in a response |

## Common mistakes

- **Manual `X-CSRF-TOKEN` headers** — prefer `makeRequest`.
- **Assuming analytics endpoints need CSRF** — they are excluded; viewing-session checks apply instead.
- **Stale tokens on long-lived PWA pages** — rely on automatic 419 refresh, or call `refreshCsrfToken()` after returning from background.

## Related docs

- [Architecture](ARCHITECTURE.md) — request pipeline and CSRF exceptions summary
- [Best practices rulebook](BEST_PRACTICES_RULEBOOK.md) — JavaScript standards
