# Bahdan Hal Portfolio (`portfolio`)

**Live website:** [Bahdan Hal — software engineering consulting and portfolio](https://bahdanhal.pl/)

Personal engineering portfolio, consulting practice, case studies, and open source work. Also available at `bahdan-hal.ovh`.

**Related projects:** [IleZa.pl — used electronics price intelligence](https://ileza.pl/) · [Stackhal — free developer and DevOps tools](https://stackhal.com/)

**Shared Packagist packages:** [`bahdan/symfony-safe-http-client`](https://packagist.org/packages/bahdan/symfony-safe-http-client) · [`bahdan/symfony-privacy-analytics-bundle`](https://packagist.org/packages/bahdan/symfony-privacy-analytics-bundle) · [`bahdan/lead-capture-bundle`](https://packagist.org/packages/bahdan/lead-capture-bundle)

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
