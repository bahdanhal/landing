# Bahdan's Ecosystem

This workspace houses the 4 core repositories powering Bahdan Hal's web services and engineering infrastructure:

```
.
├── portfolio/     # bahdanhal.pl — Personal Brand, Engineering Portfolio & Consulting
├── ileza/         # ileza.pl — Polish Second-Hand Price Radar & Financial Hub
├── stackhal/      # stackhal.com — Developer Toolbox, SEO/GEO Auditing & Infrastructure Tools
└── infra/         # Shared Caddy 2.10 Routing, Production Docker Compose & Automation
```

---

## 1. Projects Overview

### 🏛️ `portfolio/` — `bahdanhal.pl`
- **Domain**: `https://bahdanhal.pl` (also `bahdan-hal.ovh`)
- **Purpose**: Software engineering consulting, case studies, open source projects, and contact lead capture.
- **Stack**: PHP 8.5, Symfony 7.3, Caddy 2.10, Tailwind/Vanilla CSS, PostgreSQL 17.

### 🏷️ `ileza/` — `ileza.pl`
- **Domain**: `https://ileza.pl`
- **Purpose**: Polish lifestyle & used electronics price radar ("Ile za?"), historical price analytics, and employment/tax calculator (`/kalkulator-wynagrodzen`).
- **Core Routes**:
  - `/` (PL: `/pl/`): Live Used Price Index & Catalog
  - `/ceny/{slug}` (EN: `/prices/{slug}`): Product price history and median trends
  - `/kalkulator-wynagrodzen` (EN: `/salary-calculator`): UoP, Mandate, B2B tax comparison
  - `/zglos`: Product addition request
  - `/ceny/{slug}/okazja`: Community price tip submission
- **Stack**: PHP 8.5, Symfony 7.3, Doctrine ORM, PostgreSQL 17, MCP Server endpoint (`/mcp`).

### 🛠️ `stackhal/` — `stackhal.com`
- **Domain**: `https://stackhal.com`
- **Purpose**: Fast, privacy-focused online developer & infrastructure tools with zero tracking and zero login requirements.
- **Core Tools**:
  - `/caddy-transpiler`: Real-time Nginx & Apache to Caddyfile transpiler
  - `/apple-pkpass-inspector`: Apple Wallet (.pkpass) emulator & debugger
  - `/cidr-subnet-matrix`: Visual IPv4/IPv6 CIDR overlap & tree matrix
  - `/seo-audit`: Technical SEO crawler & issue diagnostics
  - `/geo-audit`: Generative Engine Optimization readiness analyzer
  - `/domain-inspector`: DMARC, BIMI, MTA-STS & SPF security inspector
  - `/bimi-studio`: BIMI SVG Tiny 1.2 PS studio & live mailbox simulator
- **Stack**: PHP 8.5, Symfony 7.3, Vanilla JS, Node 24 test runner, MCP Server (`/mcp`).

### 🌐 `infra/` — Shared Infrastructure
- **Purpose**: Unified Caddy 2.10 reverse proxy, production docker compose configurations, SSL certificate management, and background maintenance automation.
- **Files**:
  - `Caddyfile`: Multi-host reverse proxy routing `bahdanhal.pl`, `ileza.pl`, `stackhal.com` to internal FastCGI backends.
  - `docker-compose.prod.yml`: Isolated network running PostgreSQL 17 and PHP-FPM application containers.

---

## 2. Development & Verification

Each repository is fully self-contained with its own tests, PHPStan configuration, and linters:

```bash
# Run tests for ileza
cd ileza && docker run --rm -v "$PWD:/app" -w /app bahdan-landing-test vendor/bin/phpunit

# Run tests for stackhal
cd stackhal && docker run --rm -v "$PWD:/app" -w /app bahdan-landing-test vendor/bin/phpunit

# Run tests for portfolio
cd portfolio && docker run --rm -v "$PWD:/app" -w /app bahdan-landing-test vendor/bin/phpunit
```
