# Bahdan’s Toolbox

A bilingual Symfony 8.1 / PHP 8.5 collection of focused practical tools, deployed at [bahdan-hal.ovh](https://bahdan-hal.ovh/).

## Included tools

- Polish VAT net/gross calculator: browser-only arithmetic for 23%, 8%, 5%, 0% and exempt sales, plus a clearly labelled future-rate simulation.
- GEO readiness audit: one-page deterministic checks for retrieval, answer structure, schema, provenance, citations, freshness and entity clarity. AI crawler policies are reported but not scored.
- Technical SEO audit: redirects, canonical consolidation, crawl traps, parameter spaces, robots.txt, sitemap coverage and indexability.

Every indexable page has English and Polish routes, self-canonicals, reciprocal `hreflang` links and sitemap entries. Crawl result pages are `noindex`.

## Local development

Copy `.env.example` to `.env.local`, provide a strong `APP_SECRET`, then run:

```bash
docker compose --env-file .env.local up --build
```

The production server uses `/home/bahdan-landing/production.env` because Snap Docker cannot read a dotfile directly below `/home/bahdan-landing`.

## Verification

```bash
docker build --target test -t bahdan-landing-test .
docker run --rm --env-file .env.example -e APP_ENV=test bahdan-landing-test vendor/bin/phpunit
docker run --rm -v "$PWD:/app" -w /app node:24-alpine node tests/js/vat-math.test.js
```

## Crawl limits and caching

SEO and GEO audits share a fixed allowance of 10 submissions per client IP per UTC calendar day. Reports are cached for six hours. The VAT calculator performs no requests and creates no parameter URLs.

The Symfony client rejects private and reserved network destinations, uses a bounded response size and timeout, respects robots.txt for multi-page SEO crawls, limits concurrency and spaces request batches.

## Contact leads

The inline exhausted-quota form validates and honeypot-protects submissions, limits them to five per IP per UTC day, and writes monthly JSONL files only to the private `contact_leads` volume. IP addresses are HMAC-hashed. Nothing is emailed and no public route exposes the records.

Read the current file on production with:

```bash
cd /home/bahdan-landing
docker compose -p seo --env-file production.env exec app sh -lc \
  'cat /app/var/contact-leads/leads-$(date -u +%Y-%m).jsonl'
```

## Logs

- Application crawl/audit JSONL: `/app/var/audit-logs/`, retained for 14 days.
- Contact leads: `/app/var/contact-leads/`.
- Web access and PHP errors: Docker container logs.
- Certificate renewal: `/var/log/letsencrypt/` on the host.

## Production

The Docker Compose stack contains PHP-FPM and Caddy. Caddy normalizes HTTP/HTTPS and www/non-www to `https://bahdan-hal.ovh`, serves Certbot certificates and emits JSON access logs. Persistent volumes hold caches, rate limits, audit logs and contact leads.
