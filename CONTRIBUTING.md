# Contributing

Helpful changes are welcome.

## Setup

See the root [README.md](README.md) quick start and [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) for env vars, Vite, queues, and the first admin user.

## Tests

```bash
composer test
# or
php artisan test
```

Feature tests use a separate database (`traktor_v2_test` in `phpunit.xml`). Create that empty database once before running tests — details in DEVELOPMENT.

## Documentation

If a change affects behaviour, setup, routing, schema, or conventions, update the matching doc in the same commit when you can. See [Keeping docs in sync](docs/DEVELOPMENT.md#keeping-docs-in-sync).

Useful entry points: [AGENTS.md](AGENTS.md), [docs/README.md](docs/README.md).

## Pull requests

- Keep changes focused; follow the patterns in [docs/CONVENTIONS.md](docs/CONVENTIONS.md).
- Don’t commit `.env`, API keys, or other secrets.
- Clear commit messages that explain *why* help a lot.

## Security

For vulnerabilities, see [SECURITY.md](SECURITY.md) — private reporting is preferred over public issues.

## Repo settings

After Dependabot config is on `main`, double-check Dependabot alerts/security updates, secret scanning, push protection, and private vulnerability reporting in the repository settings (checklist in SECURITY.md).
