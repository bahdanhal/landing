# Bahdan’s Toolbox

A bilingual Symfony 8.1 / PHP 8.5 collection of focused practical tools, deployed at [bahdan-hal.ovh](https://bahdan-hal.ovh/).

## Included tools

- Polish contract income calculator: browser-only UoP, umowa zlecenie, umowa o dzieło and B2B comparison from one company budget using explicit 2026 assumptions.
- GEO readiness audit: one-page deterministic checks for retrieval, answer structure, schema, provenance, citations, freshness and entity clarity. AI crawler policies are reported but not scored.
- Technical SEO audit: redirects, canonical consolidation, crawl traps, parameter spaces, robots.txt, sitemap coverage and indexability.
- Poland used-price index: weekly AI market estimates stored as a validated history without server-side marketplace crawling or public marketplace citations.

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
docker run --rm -v "$PWD:/app" -w /app node:24-alpine node tests/js/income-math.test.js
```

## Crawl limits and caching

SEO and GEO audits share a fixed allowance of 10 submissions per client IP per UTC calendar day. Reports are cached for six hours. The income calculator performs no requests and creates no parameter URLs.

The Symfony client rejects private and reserved network destinations, uses a bounded response size and timeout, respects robots.txt for multi-page SEO crawls, limits concurrency and spaces request batches.

## Weekly market observations

The market vertical follows domain/application/infrastructure boundaries. Claude performs live web research through its provider; this server does not crawl or parse marketplace pages. No marketplace listing, seller, domain, quote or URL is stored, rendered or indexed. The application accepts only a consistent PLN range and a reported comparable sample. Accepted AI-estimate snapshots are stored in the private `market_data` volume.

`ANTHROPIC_MODEL` is used for short audit summaries. `MARKET_RESEARCH_MODEL` is intentionally separate so the weekly research job can use a stronger model without increasing the cost of ordinary audits.

Run the complete catalog manually with:

```bash
cd /home/bahdan-landing
docker compose -p seo --env-file production.env exec app php bin/console app:market:observe
```

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
- Market observation JSON: `/app/var/market-data/`.
- Certificate renewal: `/var/log/letsencrypt/` on the host.

## Production

The Docker Compose stack contains PHP-FPM and Caddy. Caddy normalizes HTTP/HTTPS and www/non-www to `https://bahdan-hal.ovh`, serves Certbot certificates and emits JSON access logs. Persistent volumes hold caches, rate limits, audit logs, contact leads and market history.
