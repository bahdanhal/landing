# Bahdan Hal Portfolio (`portfolio`)

Personal brand, engineering showcase, and consulting lead capture, deployed at [bahdanhal.pl](https://bahdanhal.pl/) (also `bahdan-hal.ovh`).

---

## 1. Overview

- **Landing Page (`/`, PL: `/pl/`)**: Software engineering consulting profile, featured projects, open source tools, and direct links to [ileza.pl](https://ileza.pl) and [stackhal.com](https://stackhal.com).
- **Contact Ingestion (`POST /contact`)**: Honeypot-protected and rate-limited lead capture persisted to an isolated PostgreSQL database.
- **Sitemap (`/sitemap.xml`)**: Dynamic XML sitemap with XSL stylesheet.

---

## 2. Verification

```bash
docker build --target test -t bahdan-landing-test .
docker run --rm --env-file .env.example -e APP_ENV=test bahdan-landing-test vendor/bin/phpunit --fail-on-phpunit-notice
docker run --rm bahdan-landing-test vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker run --rm bahdan-landing-test vendor/bin/phpcs
```
