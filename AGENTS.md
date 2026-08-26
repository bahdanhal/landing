# AI Agent Guidelines & System Instructions — portfolio (bahdanhal.pl)

This document provides mandatory directives for all AI coding agents interacting with the `portfolio` repository (`bahdanhal.pl`).

---

## 1. Language Constraints (CRITICAL)

- **Strict English Requirement**: All code generated, edited, or reviewed MUST be in **English only**.
  - All identifiers (classes, methods, functions, variables, constants, properties, arguments).
  - All inline comments, block comments, docblocks (`/** ... */`), and PHPDoc tags.
  - All commit messages, PR descriptions, and markdown documentation.
- **Allowed Exceptions**:
  - Translation string files in `translations/` (e.g. `messages.pl.yaml`).
  - UI copy specifically intended for Polish routes (`/pl/...`).

---

## 2. Testing & Quality Invariants (100% Green CI Required)

Before completing any task, ensure the verification matrix passes cleanly in `portfolio`:

```bash
docker run --rm -v "$PWD:/app" -w /app/portfolio -e APP_ENV=test bahdan-landing-test vendor/bin/phpunit --fail-on-phpunit-notice
docker run --rm -v "$PWD:/app" -w /app/portfolio bahdan-landing-test vendor/bin/phpstan analyse --no-progress --memory-limit=512M
docker run --rm -v "$PWD:/app" -w /app/portfolio bahdan-landing-test vendor/bin/phpcs
docker run --rm -v "$PWD:/app" -w /app/portfolio bahdan-landing-test php bin/console lint:twig templates
docker run --rm -v "$PWD:/app" -w /app/portfolio bahdan-landing-test php bin/console lint:yaml translations config
docker run --rm -v "$PWD:/app" -w /app/portfolio bahdan-landing-test composer validate --strict --no-check-publish
```

---

## 3. Domain & Architecture Directives

- **Clean Architecture & Domain-Driven Design (DDD)**:
  - Lead capture domain logic in `src/Lead/Domain/` and application orchestration in `src/Lead/Application/`.
  - Privacy analytics in `src/Analytics/`.
  - Database access via Doctrine ORM in PostgreSQL 17 (`LeadEntity`, `PageViewEntity`).
  - Public & Admin MCP tools in `src/Mcp/`.
- **MCP Diagnostics**:
  - Before changing exception handling based on historical logs, reproduce the same request with a valid initialized MCP session (`Mcp-Session-Id`).
- **Privacy Non-Negotiables**:
  - Never store raw IPs; always hash client IPs with HMAC-SHA256.
  - Rate limiting (5 leads/day per IP) and honeypot validation are mandatory.

