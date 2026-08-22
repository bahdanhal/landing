# AI Agent Guidelines & System Instructions — Bahdan’s Toolbox

This document provides mandatory directives for all AI coding agents, autonomous models, and LLM-based assistants interacting with the `bahdan-landing` repository.

---

## 1. Language Constraints (CRITICAL)

- **Strict English Requirement**: All code generated, edited, or reviewed MUST be in **English only**.
  - All identifiers (classes, methods, functions, variables, constants, properties, arguments).
  - All inline comments, block comments, docblocks (`/** ... */`), and PHPDoc tags.
  - All commit messages, PR descriptions, and markdown documentation.
- **PROHIBITION**: Never generate code comments, docblocks, or variable names in Russian, Polish, Belarusian, Ukrainian, or any other non-English language.
- **Allowed Exceptions**:
  - Domain-specific legal/tax terms in Polish contract/income calculations where no standard English term exists (`UoP`, `umowa zlecenie`, `umowa o dzieło`, `B2B`, `grosz`, `ryczałt`, `ZUS`).
  - Translation string files in `translations/` (e.g. `messages.pl.yaml`).
  - UI copy specifically intended for Polish routes (`/pl/...`) in Twig templates.

---

## 2. Testing & Quality Invariants

- **Tests Must Always Be Green**:
  - Do not finish tasks with broken tests or failing linters.
  - All PHPUnit tests and JS math tests must pass 100%.
- **Verification Commands**:
  - PHPUnit: `docker build --target test -t bahdan-landing-test . && docker run --rm --env-file .env.example -e APP_ENV=test bahdan-landing-test vendor/bin/phpunit` (or `vendor/bin/phpunit` locally).
  - Income Math JS: `docker run --rm -v "$PWD:/app" -w /app node:24-alpine node tests/js/income-math.test.js` (or `node tests/js/income-math.test.js` locally).
  - Linting: `vendor/bin/phpcs` (or `composer cs:check`), `php bin/console lint:twig templates`, `php bin/console lint:yaml translations config`.

---

## 3. Spec-Driven Development (SDD) Directives

- **Specs Are the Single Source of Truth**: All domain rules, calculation formulas, and MCP tool schemas originate from `specs/` (`income-calculator.spec.json`, `seo-audit-rules.spec.json`, `geo-readiness.spec.json`, `mcp-tools.spec.json`).
- **Do Not Drift**: When modifying or adding calculation logic, audit rules, or tools, always consult and update the JSON specification in `specs/` first.
- **Specification Compliance**: All changes must pass `tests/Spec/SpecificationComplianceTest.php`.

---

## 4. Architecture & Design Principles

- **Clean Architecture & Domain-Driven Design (DDD)**:
  - Isolate business logic in `src/<BoundedContext>/Domain/` (pure PHP, zero external/framework dependencies).
  - Orchestrate use cases in `src/<BoundedContext>/Application/`.
  - Put external adapters, file storage, and AI integrations in `src/<BoundedContext>/Infrastructure/`.
  - Keep presentation in `src/Controller/` and `src/Command/`.
- **SOLID Principles**:
  - Single Responsibility: Keep classes small and cohesive.
  - Open/Closed: Program to interfaces (e.g. `AiClient`, `HttpFetcher`, `PriceObservationRepository`).
  - Dependency Inversion: Inject abstractions via constructors.
- **Clean Code & PHP Standards**:
  - Always add `declare(strict_types=1);` at the top of PHP files.
  - Use native PHP 8.5 type declarations everywhere.
  - Use integer `grosz` for currency math to prevent floating point inaccuracy.
  - Use immutable Value Objects (`readonly` classes/properties) where possible.
  - Follow PSR-12 code style standards.

---

## 5. Local Execution & Docker Recommendations

- Recommend running and executing all commands inside Docker containers to preserve environment parity (PHP 8.5, Caddy 2.10, Alpine, exact extensions).
- Run console commands via `docker compose --env-file .env.local exec app php bin/console <command>`.

---

## 6. Security & Privacy Non-Negotiables

- **Database & Network Isolation**: The PostgreSQL database runs exclusively within the private internal Docker network without mapped host ports. External network access to port 5432 is strictly forbidden. Database credentials must be defined only in `production.env` on the host.
- **SSRF Guard**: Always validate target URLs against private/reserved IP ranges and pin DNS resolution.
- **Marketplace tip privacy**: Marketplace URLs may be accepted only when a visitor voluntarily submits a public listing for Bahdan's private manual price review. Never fetch these URLs automatically, publish them, store seller details or listing text, or retain query parameters/fragments. Keep each normalized URL in storage for at most 90 days, restrict it to the authenticated admin view, and hash the submitter IP address with HMAC-SHA256 before persistence.
- **Logging privacy**: Never write marketplace URLs, seller information, raw query parameters, email addresses, phone numbers, or contact messages to application logs. Always hash client IP addresses with HMAC-SHA256 before persistence.
