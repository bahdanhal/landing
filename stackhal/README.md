# Stackhal (`stackhal`)

Privacy-focused developer and DevOps toolbox, deployed at [stackhal.com](https://stackhal.com/).

---

## 1. Included Tools & Canonical Routes

- **`/caddy-transpiler`**: Real-time Nginx server blocks and Apache `.htaccess` to modern Caddyfile converter.
- **`/apple-pkpass-inspector`**: Apple Wallet (`.pkpass`) archive parser, SHA-1 manifest integrity checker, PKCS#7 certificate inspector, and Google Wallet converter.
- **`/cidr-subnet-matrix`**: Visual IPv4/IPv6 CIDR overlap calculator, collision detector, and 2D binary tree matrix.
- **`/seo-audit`**: Technical SEO crawler, canonical auditor, redirect loop detector, and crawl trap identifier with SSRF protection.
- **`/geo-audit`**: Generative Engine Optimization (GEO) readiness analyzer (13 deterministic criteria for AI search engines like Perplexity, ChatGPT, Gemini).
- **`/domain-inspector`**: Domain email security analyzer for DMARC, BIMI, MTA-STS, TLS-RPT, SPF, and MX records.
- **`/bimi-studio`**: BIMI SVG Tiny 1.2 PS studio, sanitizer, and live avatar mailbox preview (Gmail, Apple Mail, Yahoo).
- **`POST /mcp`**: High-performance Model Context Protocol endpoint exposing all tool operations to AI agents.

---

## 2. Verification

```bash
docker build --target test -t bahdan-landing-test .
docker run --rm --env-file .env.example -e APP_ENV=test bahdan-landing-test vendor/bin/phpunit --fail-on-phpunit-notice
docker run --rm -v "$PWD:/app" -w /app node:24-alpine sh -lc 'for test in tests/js/*.test.js; do node "$test" || exit 1; done'
docker run --rm bahdan-landing-test vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker run --rm bahdan-landing-test vendor/bin/phpcs
```
