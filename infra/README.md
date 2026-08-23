# Infra / Production Deployment Orchestrator

This infrastructure repository orchestrates production services for the Bahdan Ecosystem:
- **`stackhal.com`**: Global Dev Tools & AI Endpoint
- **`ileza.pl`**: Polish Lifestyle, Price Radar & Tax Hub
- **`bahdanhal.pl`**: Portfolio & Engineering Profile

## Architecture
- **Reverse Proxy**: Caddy 2.10 with automated TLS certificates (ports 80/443).
- **Database**: PostgreSQL 17 on internal Docker network (zero public port exposures).
- **Isolated DBs**: `bahdan_prod`, `ileza_prod`, `stackhal_prod`.

## Deployment
```bash
docker compose -f docker-compose.prod.yml up -d --build
```
