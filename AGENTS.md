# AI Agent Guidelines & System Instructions — Bahdan’s Ecosystem

This document provides mandatory directives for all AI coding agents, autonomous models, and LLM-based assistants interacting with the repositories in the `bahdan-landing` ecosystem workspace (`portfolio`, `ileza`, `stackhal`, `infra`).

---

## 1. Language Constraints (CRITICAL)

- **Strict English Requirement**: All code generated, edited, or reviewed MUST be in **English only**.
  - All identifiers (classes, methods, functions, variables, constants, properties, arguments).
  - All inline comments, block comments, docblocks (`/** ... */`), and PHPDoc tags.
  - All commit messages, PR descriptions, and markdown documentation.
- **Allowed Exceptions**:
  - Domain-specific legal/tax terms in Polish contract/income calculations where no standard English term exists (`UoP`, `umowa zlecenie`, `umowa o dzieło`, `B2B`, `grosz`, `ryczałt`, `ZUS`).
  - Translation string files in `translations/` (e.g. `messages.pl.yaml`).
  - UI copy specifically intended for Polish routes (`/pl/...` or Polish templates).

---

## 2. Testing & Quality Invariants (100% Green CI Required)

Before completing any task, ensure the verification matrix passes cleanly in the target repository (`ileza`, `stackhal`, or `portfolio`):

```bash
# Verify ileza
docker run --rm -v "$PWD:/app" -w /app/ileza -e APP_ENV=test bahdan-landing-test vendor/bin/phpunit --fail-on-phpunit-notice
docker run --rm -v "$PWD:/app" -w /app/ileza bahdan-landing-test vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker run --rm -v "$PWD:/app" -w /app/ileza bahdan-landing-test vendor/bin/phpcs

# Verify stackhal
docker run --rm -v "$PWD:/app" -w /app/stackhal -e APP_ENV=test bahdan-landing-test vendor/bin/phpunit --fail-on-phpunit-notice
docker run --rm -v "$PWD:/app" -w /app/stackhal -e APP_ENV=test node:24-alpine sh -lc 'for test in tests/js/*.test.js; do node "$test" || exit 1; done'
docker run --rm -v "$PWD:/app" -w /app/stackhal bahdan-landing-test vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker run --rm -v "$PWD:/app" -w /app/stackhal bahdan-landing-test vendor/bin/phpcs

# Verify portfolio
docker run --rm -v "$PWD:/app" -w /app/portfolio -e APP_ENV=test bahdan-landing-test vendor/bin/phpunit --fail-on-phpunit-notice
docker run --rm -v "$PWD:/app" -w /app/portfolio bahdan-landing-test vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker run --rm -v "$PWD:/app" -w /app/portfolio bahdan-landing-test vendor/bin/phpcs
```

---

## 3. Architecture & Domain Responsibilities

- **`portfolio/` (`bahdanhal.pl`)**: Engineering consulting, portfolio showcase, contact lead ingestion.
- **`ileza/` (`ileza.pl`)**: Used electronics price radar, Polish salary/tax calculator, manually curated price observations, community price tip submissions.
- **`stackhal/` (`stackhal.com`)**: Online developer tools (Caddyfile transpiler, Apple PKPass inspector, CIDR matrix, SEO/GEO audit, DMARC domain inspector).
- **`infra/`**: Shared Caddy 2.10 reverse proxy and production docker compose.
