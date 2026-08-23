# IleZa.pl (`ileza`)

Poland's used electronics price radar and salary/income calculator, deployed at [ileza.pl](https://ileza.pl/).

---

## 1. Overview

**IleZa.pl** ("Ile za?") provides transparent, manually verified asking price benchmarks for second-hand electronics and consumer vehicles in Poland, alongside a browser-only Polish employment and tax comparison calculator.

### Core Features & Canonical Routes:
- **Price Radar & Catalog (`/`, PL: `/pl/`)**: Live catalog of product families (smartphones, laptops, cars, RAM).
- **Product History (`/ceny/{slug}`, EN: `/prices/{slug}`)**: Detailed 30-day and 90-day price trends, median asking prices, sample size, and market condition ratings.
- **Employment & Tax Calculator (`/kalkulator-wynagrodzen`, EN: `/salary-calculator`)**: Browser-only comparison of UoP, Umowa Zlecenie, Umowa o Dzieło, and B2B from a single company employer budget using 2026 Polish tax rules.
- **Product Addition Request (`/zglos`, EN: `/request`)**: Community submission queue for new models.
- **Price Tip Submission (`/ceny/{slug}/okazja`, EN: `/prices/{slug}/price-tip`)**: Community price alerts for admin review.
- **Model Context Protocol (`POST /mcp`)**: MCP tools for price lookups, tax calculations, and authenticated admin operations.

---

## 2. Verification

```bash
docker build --target test -t bahdan-landing-test .
docker run --rm --env-file .env.example -e APP_ENV=test bahdan-landing-test vendor/bin/phpunit --fail-on-phpunit-notice
docker run --rm bahdan-landing-test vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker run --rm bahdan-landing-test vendor/bin/phpcs
```

---

## 3. Privacy & Security Invariants

- **Zero Marketplace Scraping**: The service never crawls marketplace pages automatically.
- **Voluntary Community Price Tips**: Submitted URLs are stripped of tracking parameters, never fetched automatically, stored privately for manual review, and automatically pruned after 90 days.
- **IP Hashing**: Client IP addresses are irreversibly hashed using HMAC-SHA256 before persistence.
- **Database Isolation**: PostgreSQL operates exclusively within an internal Docker network.
