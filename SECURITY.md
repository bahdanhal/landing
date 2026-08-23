# Security policy

## Supported version

Security fixes are applied to the latest commit on the default branch. Older revisions and forks are not supported.

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability, exposed credential, or privacy incident. Use GitHub's private vulnerability reporting feature for this repository. If that feature is unavailable, contact the maintainer through the email address published on [bahdanhal.pl](https://bahdanhal.pl/).

Include the affected component, reproduction steps, impact, and any suggested mitigation. Do not access data that does not belong to you, perform denial-of-service testing, or publish details before a fix is available.

The maintainer will acknowledge a complete report within seven days and will coordinate disclosure after the issue has been assessed and remediated.

## Security boundaries

- PostgreSQL must remain private and must never publish port 5432 to the host.
- All user-supplied crawl targets must pass the SSRF guard and pinned DNS resolution.
- Marketplace listing URLs and contact details are private administrative data and must never appear in logs or public responses.
- Secrets belong only in local or production environment files and GitHub Actions secrets, never in the repository.
