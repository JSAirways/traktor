# Traktor documentation

Developer and technical documentation for the Traktor family video gallery platform.

| Document | Description |
|----------|-------------|
| [Technical brief](TECHNICAL_BRIEF.md) | Product purpose, roles, capabilities, and feature map |
| [Architecture](ARCHITECTURE.md) | Sessions, device identity, services, middleware |
| [Development](DEVELOPMENT.md) | Local setup, env vars, workflows, conventions |
| [Schema notes](SCHEMA_NOTES.md) | Core tables, relationships, local admin bootstrap |
| [Best practices rulebook](BEST_PRACTICES_RULEBOOK.md) | Coding patterns and standards for active development |
| [CSRF token guide](CSRF_TOKEN_GUIDE.md) | CSRF exceptions, `makeRequest`, token refresh |

Start with the **technical brief** for product context, then **architecture** for how the pieces fit together. Use **development** for day-to-day setup and the **rulebook** when implementing features.

When committing and pushing code, follow **[Documentation on commit & push](DEVELOPMENT.md#documentation-on-commit--push)** in the development guide — update the relevant docs in the same commit when your change affects behaviour, setup, or conventions.
