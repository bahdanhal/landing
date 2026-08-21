# Open SEO Audit

A fast, deterministic technical SEO auditor built with PHP 8.5 and Symfony 8.1. It performs a bounded, robots-aware crawl and reports evidence for redirect inconsistencies, missing or conflicting canonicals, parameter-generated URL spaces, pagination traps, duplicate content, broken internal URLs, robots.txt, and sitemap problems.

No AI, database, account, or queue is required. The web report and JSON API use the same audit engine. An optional Claude summary can explain and prioritize the deterministic findings.
Public audit requests are limited to ten per IP per UTC day; CLI audits are not limited. The daily counters use a persistent Docker volume, are shared by the web and JSON API, and survive container replacement.

Audits cannot run reliably in frontend JavaScript because arbitrary sites normally do not grant browsers cross-origin HTML access. The server crawler therefore stays bounded and polite: three concurrent requests by default, a delay between batches, robots.txt enforcement, a small sitemap sample, and a persistent six-hour report cache.

## Run locally

```bash
cp .env.example .env.local
# Set APP_SECRET in .env.local
docker compose --env-file .env.local up --build
```

Open `http://localhost:8080`.

CLI:

```bash
docker compose exec app php bin/console seo:audit https://example.com
docker compose exec app php bin/console seo:audit https://example.com --summary
docker compose exec app php bin/console seo:audit https://example.com --refresh
```

API:

```bash
curl -X POST http://localhost:8080/api/audit \
  -H 'Content-Type: application/json' \
  -d '{"url":"https://example.com"}'
```

## Hetzner deployment

The production deployment lives independently at `/home/seo`. Copy `.env.example` to `production.env`, set a long random `APP_SECRET`, and run `docker compose --env-file production.env up -d --build`. The non-hidden filename is required because Snap Docker blocks dotfiles placed directly under `/home/<name>`. Caddy serves `bahdan-hal.ovh` on ports 80 and 443 using the Certbot certificate mounted read-only from `/etc/letsencrypt`. Do not expose PHP-FPM publicly.

Certbot uses `/home/seo/certbot-webroot` for HTTP validation. Its systemd timer renews certificates, and `/etc/letsencrypt/renewal-hooks/deploy/seo-reload-web` restarts only the Caddy container after a successful renewal. Docker's `restart: unless-stopped` policy keeps both application containers running.

Resource controls are environment variables:

- `AUDIT_MAX_PAGES` — bounded crawl size, default 40.
- `AUDIT_CONCURRENCY` — concurrent requests per crawl wave, default 3.
- `AUDIT_BATCH_DELAY_MS` — minimum pause between crawl waves, default 200 ms. A larger robots.txt crawl-delay wins.
- `AUDIT_TIMEOUT_SECONDS` — per-request timeout, default 12.
- `AUDIT_MAX_BODY_BYTES` — response body ceiling, default 2 MB.
- `AUDIT_CACHE_TTL` — cached report lifetime, default 21,600 seconds (six hours).
- `AUDIT_LOG_DIRECTORY` — persistent structured log directory, default `/app/var/audit-logs`.
- `AUDIT_LOG_RETENTION_DAYS` — daily audit-log retention, default 14 days.
- `CONTACT_LEAD_DIRECTORY` — private server-only email lead storage, default `/app/var/contact-leads`.

The `audit_cache` Docker volume preserves cached reports across container replacement. Submit `refresh: true` to the JSON API, use the report’s **Refresh crawl** button, or pass `--refresh` on the CLI to bypass the cache.

## Logs

Every audit writes structured JSON events for the request, sitemap inspection, each fetched page, scoring, completion, and failure. Query-string values are removed before logging; parameter names remain so parameter crawl behavior can still be diagnosed.

Follow the normal application and web-server output:

```bash
docker compose logs --tail=200 -f app web
```

Read today's persistent crawl log:

```bash
docker compose exec app sh -lc 'tail -n 200 /app/var/audit-logs/audit-$(date -u +%F).jsonl'
```

List all retained daily logs:

```bash
docker compose exec app ls -lh /app/var/audit-logs
```

The `audit_logs` Docker volume survives rebuilds and container replacement. Logs are deliberately not exposed through a public web route because they may contain target paths and error details.

## Contact leads

The report dialog and exhausted-quota page accept an email address. Submissions are validated, honeypot-protected, limited to five per IP per UTC day, and stored only in the private `contact_leads` Docker volume. IP addresses are HMAC-hashed before storage. No email is sent and no public route exposes the records.

List saved lead files:

```bash
docker compose exec app ls -lh /app/var/contact-leads
```

Read the current month:

```bash
docker compose exec app sh -lc 'cat /app/var/contact-leads/leads-$(date -u +%Y-%m).jsonl'
```

## Optional Claude summary

Create a new Anthropic key and keep it only in `.env.local` on the server:

```dotenv
ANTHROPIC_API_KEY=your-new-key
ANTHROPIC_MODEL=your-current-low-cost-model-id
```

Both values must be set to enable the summary. The model receives only the score, crawl counts, and up to 24 deterministic findings. If configuration is absent, the request fails, or the response is invalid, no AI panel is rendered. A failed AI call never fails the audit.

## Design

1. Validate the destination and reject private/reserved IP ranges (SSRF protection).
2. Test HTTP/HTTPS, www/non-www, and slash variants.
3. Fetch robots.txt and recursively inspect sitemap indexes.
4. Always inspect the submitted URL, then crawl internal HTML pages breadth-first with hard limits.
5. Probe arbitrary query strings and extreme pagination only with safe GET requests.
6. Apply deterministic, testable rules and return evidence alongside every finding.

AI can later summarize or prioritize the deterministic report, but it should not replace the underlying HTTP and DOM checks.
