# Security

## Supported versions

Fixes land on the latest commit on `main`.

## Reporting a vulnerability

Please use **GitHub Private Vulnerability Reporting** for this repository (Security tab → Report a vulnerability).

Prefer not to open a public GitHub issue for exploitable bugs, and please don’t post proof-of-concept exploits publicly before a fix is available.

Reports are appreciated — I’ll follow up as soon as I’m able. Include enough detail to reproduce the issue (affected paths, role/device setup, and expected vs actual behaviour).

## Scope

**In scope**

- Authentication and session handling (parent/admin login)
- Device trust (`device_uid` / `device_token`) and viewing-session unlock
- PIN handling and related rate limiting
- Authorization between families, parents, and child profiles
- XSS, CSRF, and similar web vulnerabilities
- Accidental exposure of secrets or credentials in the repository

**Out of scope**

- Denial of service / resource exhaustion without a clear security boundary bypass
- Issues that require physical access to an unlocked device where a correct viewing PIN is already known
- Misconfiguration of a self-hosted deployment (weak `APP_KEY`, HTTP-only production, debug left on, etc.) unless it reveals a code defect
- Feature requests or ordinary bugs (normal issues/PRs are fine for those)

## Repo settings checklist

Handy checklist for GitHub → Settings → Code security and analysis:

- [ ] Private vulnerability reporting enabled
- [ ] Secret scanning enabled
- [ ] Push protection for secrets enabled
- [ ] Dependabot alerts and Dependabot security updates enabled
