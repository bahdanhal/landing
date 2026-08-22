# Bahdan’s Toolbox — outcomes and operations

This is the standalone toolbox project, separate from BramClassAuto.

## Live services

- Website: https://bahdanhal.pl
- Sitemap: https://bahdanhal.pl/sitemap.xml
- Public MCP endpoint: https://bahdanhal.pl/mcp
- Legacy redirect: https://bahdan-hal.ovh -> https://bahdanhal.pl (301)
- Production directory: `/home/bahdan-landing`
- Compose project: `seo`
- Production environment file: `/home/bahdan-landing/production.env`

## Server connection

```sh
ssh -i ~/.ssh/id_ed25519 "$SSH_USER@$SSH_HOST"
cd /home/bahdan-landing
```

The SSH key and `production.env` are private. Do not commit either one.

## Automated deployment via GitHub Actions

Pushing to `master` automatically triggers the `.github/workflows/deploy.yml` workflow, which runs tests and deploys to the production server.

### Required GitHub Repository Secrets
Under **Settings > Secrets and variables > Actions** on GitHub:
- `SSH_PRIVATE_KEY`: Private SSH key authorized on the production host (e.g. contents of `~/.ssh/id_ed25519`).
- `SSH_HOST`: Production host name or IP address.
- `SSH_USER`: Restricted deployment account on the production host.

---

## Manual deploy from local checkout

Run from the local `bahdan-landing` checkout:

```sh
rsync -az --exclude=.git --exclude=.github --exclude=.idea --exclude=production.env \
  --exclude=certbot-webroot --exclude=.env.local --exclude=var/ \
  -e 'ssh -i ~/.ssh/id_ed25519' ./ "$SSH_USER@$SSH_HOST:/home/bahdan-landing/"

ssh -i ~/.ssh/id_ed25519 "$SSH_USER@$SSH_HOST" \
  'cd /home/bahdan-landing && docker compose -p seo --env-file production.env up -d --build'
```

Check service health and recent logs:

```sh
ssh -i ~/.ssh/id_ed25519 "$SSH_USER@$SSH_HOST" \
  'cd /home/bahdan-landing && docker compose -p seo --env-file production.env ps'

ssh -i ~/.ssh/id_ed25519 "$SSH_USER@$SSH_HOST" \
  'cd /home/bahdan-landing && docker compose -p seo --env-file production.env logs --tail=100 app web'
```

## What is live

- Personal landing page at `/` and toolbox index at `/tools`.
- Polish and English routes for the landing page and every tool.
- Technical SEO audit with polite crawling, caching, grouped findings, robots/sitemap checks, and a 10-audits-per-IP-per-day limit.
- GEO analyzer with optional AI summary.
- UoP / UZ / Ud / B2B Polish income calculator.
- Poland used-goods price index with grouped configuration dropdowns, bilingual product pages, manually reviewed observations, open MCP access, and Wikimedia-licensed product photos.
- Peugeot 206 CC family with 1.6 and 2.0 petrol configurations.
- Private product-request and cheaper-price-tip forms; submissions are stored on the server and are not emailed.
- Sitemap contains only product pages with stored observations. Empty product pages are `noindex,follow` until their first observation.
- XML sitemap has a browser-readable XSL view while remaining valid XML for search engines.

## Manual market review

Market JSON histories live in the Docker volume mounted at `/app/var/market-data`. Product requests are in `/app/var/market-data/requests/`; voluntarily submitted price-tip links are kept in the private `/app/var/market-data/price-tips/` queue for at most 90 days.

Review submitted public-listing links manually. Never fetch them automatically or copy seller data or listing text. Enter only an aggregate, dated observation through the authenticated admin interface. The public methodology and the private handling rules are documented in `docs/market-price-tips.md`.

Set a dedicated high-entropy `MARKET_ADMIN_TOKEN` in `production.env` to enable the administrative MCP tools. Configure the private MCP client to send it only as an `Authorization: Bearer` header. The tools provide submission statistics, consultation leads, product requests and active price tips; full setup and incident-rotation guidance is in `docs/admin-mcp.md`.

Symfony AI remains available only for optional audit summaries. Provider and summary-model settings are in `production.env` (`AI_PROVIDER`, `AI_SUMMARY_MODEL`). Do not put API keys in the repository or this note.

## Verification

From the local checkout:

```sh
docker build --target test -t bahdan-landing-test .
docker run --rm bahdan-landing-test
docker run --rm bahdan-landing-test php bin/console lint:twig templates
docker run --rm bahdan-landing-test php bin/console lint:yaml translations config
```

Useful public checks:

```sh
curl -I https://bahdanhal.pl/healthz
curl -I https://bahdanhal.pl/sitemap.xml
curl -I https://bahdanhal.pl/robots.txt
curl -I https://bahdan-hal.ovh/ # Should return 301 to https://bahdanhal.pl/
```

## Recent milestones

- `3c3f006` — restrained dark blue visual theme.
- `c7e6012` — Peugeot family, request flow, and lighter gradient theme.
- `23004a5` — preserve approved retrospective metadata internally.
- `252813b` — exclude empty product pages from the sitemap and indexing.
- `ec5644d` — add readable XML sitemap stylesheet.
- `6d612bb` — reduce sitemap browser caching to five minutes.
