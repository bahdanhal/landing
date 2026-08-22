# Bahdan’s Toolbox

A bilingual Symfony 8.1 / PHP 8.5 collection of focused practical tools, deployed at [bahdanhal.pl](https://bahdanhal.pl/).

## Included tools

- Polish contract income calculator: browser-only UoP, umowa zlecenie, umowa o dzieło and B2B comparison from one company budget using explicit 2026 assumptions.
- GEO readiness audit: one-page deterministic checks for retrieval, answer structure, schema, provenance, citations, freshness and entity clarity. AI crawler policies are reported but not scored.
- Technical SEO audit: redirects, canonical consolidation, crawl traps, parameter spaces, robots.txt, sitemap coverage and indexability.
- Poland used-goods price index: manually reviewed asking-price snapshots with a private, rate-limited community tip queue and no marketplace crawling.

Every indexable page has English and Polish routes, self-canonicals, reciprocal `hreflang` links and sitemap entries. Crawl result pages are `noindex`.

## Local development

Copy `.env.example` to `.env.local`, replace both placeholder secrets with strong random values, then run:

```bash
docker compose --env-file .env.local up --build
```

Compose waits for PostgreSQL, applies versioned Doctrine migrations, and then starts the application. The database is available only on the private Compose network.

## Verification

```bash
docker build --target test -t bahdan-landing-test .
docker run --rm --env-file .env.example -e APP_ENV=test bahdan-landing-test vendor/bin/phpunit --fail-on-phpunit-notice
docker run --rm -v "$PWD:/app" -w /app node:24-alpine sh -lc 'for test in tests/js/*.test.js; do node "$test" || exit 1; done'
docker run --rm bahdan-landing-test vendor/bin/phpcs
docker run --rm bahdan-landing-test vendor/bin/phpstan analyse --no-progress --memory-limit=512M
```

## Crawl limits and caching

SEO and GEO audits share a fixed allowance of 10 submissions per client IP per UTC calendar day. Reports are cached for six hours. The income calculator performs no requests and creates no parameter URLs.

The Symfony client rejects private and reserved network destinations, uses a bounded response size and timeout, respects robots.txt for multi-page SEO crawls, limits concurrency and spaces request batches.

## Manually reviewed market observations

Bahdan reviews small samples of comparable public asking prices and records only aggregate observations through the authenticated admin panel. The server never crawls marketplace pages. Visitors may submit a listing URL as a private price tip; query strings and fragments are stripped, no page is fetched automatically, the IP address is HMAC-hashed, and the database record is removed after 90 days. Submitted links are visible only in the authenticated admin review queue and are never republished.

The complete submission and retention policy is documented in [docs/market-price-tips.md](docs/market-price-tips.md).

The MCP endpoint also exposes Bearer-protected administrative tools for viewing consultation leads, product requests, active price tips, recent SEO audit outcomes, submission trends and market coverage. Tokens are supplied only through the HTTP `Authorization` header; they are never tool arguments. Setup and privacy rules are documented in [docs/admin-mcp.md](docs/admin-mcp.md).

`AI_SUMMARY_MODEL` is used only for optional English summaries of deterministic SEO audit evidence. It does not participate in market observations.

## Contact leads

The inline exhausted-quota form validates and honeypot-protects submissions, limits them to five per IP per UTC day, and stores them in the private PostgreSQL database. IP addresses are HMAC-hashed. Nothing is emailed and no public route exposes the records.

## Logs

- Application crawl/audit JSONL: `/app/var/audit-logs/`, retained for 14 days.
- Contact leads, market records, community submissions, and privacy-preserving analytics: PostgreSQL on the private network.
- Web access and PHP errors: Docker container logs.

## Production

The Docker Compose stack contains PostgreSQL, PHP-FPM, and Caddy. Caddy normalizes the production domains, serves certificates, and emits JSON access logs. Persistent volumes hold the database, caches, rate limits, and audit logs.

## Development standards & guidelines

- **[CONTRIBUTING.md](CONTRIBUTING.md)**: Engineering standards, strict English language policy for code/comments, Clean Code, SOLID, Domain-Driven Design (DDD), Docker workflows, and testing quality gates.
- **[AGENTS.md](AGENTS.md)**: Instructions and constraints for AI coding assistants.
- **[ARCHITECTURE.md](ARCHITECTURE.md)**: Detailed system architecture, bounded contexts, PostgreSQL persistence, and SSRF security design.
- **[docs/admin-mcp.md](docs/admin-mcp.md)**: Administrative MCP authentication, tools, operation, and private-data handling.

## License

The application source code is available under the [MIT License](LICENSE). Asset-specific terms, third-party image attribution, and the files excluded from the MIT grant are documented in [NOTICE.md](NOTICE.md).
