# Operations guide

This document describes the public, provider-neutral deployment contract. Hostnames, server addresses, user accounts, certificate paths, and credentials belong in private operator documentation.

## Services

The Compose stack contains:

- `db`: PostgreSQL 17 on the private `internal` network, with no published host port.
- `migrate`: a one-shot Doctrine migration service that must complete before the application starts.
- `app`: a read-only PHP-FPM application container.
- `web`: a read-only Caddy container publishing HTTP and HTTPS.

Persistent named volumes retain PostgreSQL data, audit cache and logs, rate limits, and legacy import directories. Environment-specific certificates are mounted read-only into the web container.

## Configuration

Copy `.env.example` to a private environment file and replace at least:

- `APP_SECRET`
- `POSTGRES_PASSWORD`
- `MARKET_ADMIN_TOKEN` when administrative MCP tools are enabled
- optional AI provider credentials

Compose derives `DATABASE_URL` from the PostgreSQL settings. Set `DATABASE_URL` explicitly only when using a separately managed database; URL-encode credentials when necessary.

Never commit the populated environment file. Keep PostgreSQL private and do not add a host port mapping for port 5432.

## Deploy

Build and start the stack with an operator-managed environment file:

```sh
docker compose --env-file /secure/path/production.env up -d --build
```

Compose waits for PostgreSQL readiness, applies pending Doctrine migrations, and starts the application only after migration succeeds.

Legacy file-backed records can be imported once after upgrading an older installation:

```sh
docker compose --env-file /secure/path/production.env exec app \
  php bin/console app:import-json-to-database
```

Data retention (page views, price tips, audit logs) can be pruned periodically via a scheduled cron or systemd timer:

```sh
docker compose --env-file /secure/path/production.env exec app \
  php bin/console app:prune-expired-data
```

Back up the PostgreSQL volume before migrations and retain a tested restore procedure. Do not use `doctrine:schema:update --force` in production.

## Verification

Before deployment, run the commands documented in `README.md`. After deployment, verify the health endpoint, sitemap, robots file, representative localized pages, and application logs.

```sh
curl -fI https://example.com/healthz
curl -fI https://example.com/sitemap.xml
curl -fI https://example.com/robots.txt
docker compose --env-file /secure/path/production.env ps
docker compose --env-file /secure/path/production.env logs --tail=100 app web migrate
```

## GitHub Actions deployment

The included deployment workflow is specific to the canonical hosted instance. Public forks should disable or replace it. The `production` GitHub Environment should require reviewer approval and restrict deployments to the protected default branch.

Required repository secrets are:

- `SSH_PRIVATE_KEY`: a dedicated key for a restricted deployment account.
- `SSH_HOST`: the deployment host.
- `SSH_USER`: the restricted deployment account name.

Rotate credentials immediately if a workflow log, artifact, or repository history exposes them.
