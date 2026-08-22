# Bahdan’s Toolbox — outcomes and operations

This is the standalone toolbox project, separate from BramClassAuto.

## Live services

- Website: https://bahdan-hal.ovh
- Sitemap: https://bahdan-hal.ovh/sitemap.xml
- Public MCP endpoint: https://bahdan-hal.ovh/mcp
- Production directory: `/home/bahdan-landing`
- Compose project: `seo`
- Production environment file: `/home/bahdan-landing/production.env`

## Server connection

```sh
ssh -i ~/.ssh/id_ed25519 root@62.238.1.164
cd /home/bahdan-landing
```

The SSH key and `production.env` are private. Do not commit either one.

## Automated deployment via GitHub Actions

Pushing to `master` automatically triggers the `.github/workflows/deploy.yml` workflow, which runs tests and deploys to the production server.

### Required GitHub Repository Secrets
Under **Settings > Secrets and variables > Actions** on GitHub:
- `SSH_PRIVATE_KEY`: Private SSH key authorized for `root@62.238.1.164` (e.g. contents of `~/.ssh/id_ed25519`).
- `SSH_HOST` (optional): `62.238.1.164` (defaults to server IP).
- `SSH_USER` (optional): `root` (defaults to `root`).

---

## Manual deploy from local checkout

Run from the local `bahdan-landing` checkout:

```sh
rsync -az --exclude=.git --exclude=.idea --exclude=production.env \
  --exclude=certbot-webroot --exclude=.env.local --exclude=var/ \
  -e 'ssh -i ~/.ssh/id_ed25519' ./ root@62.238.1.164:/home/bahdan-landing/

ssh -i ~/.ssh/id_ed25519 root@62.238.1.164 \
  'cd /home/bahdan-landing && docker compose -p seo --env-file production.env up -d --build'
```

Check service health and recent logs:

```sh
ssh -i ~/.ssh/id_ed25519 root@62.238.1.164 \
  'cd /home/bahdan-landing && docker compose -p seo --env-file production.env ps'

ssh -i ~/.ssh/id_ed25519 root@62.238.1.164 \
  'cd /home/bahdan-landing && docker compose -p seo --env-file production.env logs --tail=100 app web'
```

## What is live

- Personal landing page at `/` and toolbox index at `/tools`.
- Polish and English routes for the landing page and every tool.
- Technical SEO audit with polite crawling, caching, grouped findings, robots/sitemap checks, and a 10-audits-per-IP-per-day limit.
- GEO analyzer with optional AI summary.
- UoP / UZ / Ud / B2B Polish income calculator.
- Poland used-goods price index with grouped configuration dropdowns, bilingual product pages, historical estimates, open MCP access, and Wikimedia-licensed product photos.
- Peugeot 206 CC family with 1.6 and 2.0 petrol configurations.
- Private product-request form; requests are stored on the server and are not emailed.
- Sitemap contains only product pages with stored observations. Empty product pages are `noindex,follow` until their first observation.
- XML sitemap has a browser-readable XSL view while remaining valid XML for search engines.

## Market research and cost control

The normal observation command batches products into one AI research call instead of calling the model once per configuration:

```sh
docker compose -p seo --env-file production.env exec -T app \
  php bin/console app:market:observe --families
```

For a dated estimate:

```sh
docker compose -p seo --env-file production.env exec -T app \
  php bin/console app:market:observe --families --at=YYYY-MM-DD
```

Market JSON histories live in the Docker volume mounted at `/app/var/market-data`. Product requests are in `/app/var/market-data/requests/`.

The service is provider-agnostic through Symfony AI. Provider and model settings are in `production.env` (`AI_PROVIDER`, `AI_RESEARCH_MODEL`, `AI_SUMMARY_MODEL`). Do not put API keys in the repository or this note.

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
curl -I https://bahdan-hal.ovh/healthz
curl -I https://bahdan-hal.ovh/sitemap.xml
curl -I https://bahdan-hal.ovh/robots.txt
```

## Recent milestones

- `3c3f006` — restrained dark blue visual theme.
- `c7e6012` — Peugeot family, request flow, batched market research, and lighter gradient theme.
- `23004a5` — preserve approved retrospective metadata internally.
- `252813b` — exclude empty product pages from the sitemap and indexing.
- `ec5644d` — add readable XML sitemap stylesheet.
- `6d612bb` — reduce sitemap browser caching to five minutes.

