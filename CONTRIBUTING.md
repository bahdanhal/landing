# Contributing & Development Standards — Bahdan’s Toolbox

This document defines the engineering standards, architecture principles, language requirements, and development workflows for **Bahdan’s Toolbox** (`bahdan-landing`).

All contributors and AI coding assistants must adhere to these guidelines.

---

## 1. Language Policy for Code & AI Agents

### Strict English Rule for Code and Technical Documentation
- **All source code must be written exclusively in English**:
  - Class, interface, trait, enum, and method names
  - Variable and constant names
  - Inline comments, docblocks (`/** ... */`), and PHPDoc annotations
  - Commit messages, PR titles, and PR descriptions
  - Technical documentation, architecture notes, and issue tracker entries
- **AI Agent Directive**: AI coding assistants (e.g. Cursor, Claude, Antigravity, Copilot, ChatGPT) **must never** insert comments, docstrings, explanations, or identifiers in languages other than English (e.g. no Russian, Polish, Belarusian, etc.).

### Permitted Exceptions
The only exceptions to the English-only rule are:
1. **Domain-Specific Terms (Ubiquitous Language)**:
   - Specific Polish legal, tax, or employment terms that are exact domain concepts with no direct 1:1 English equivalent in Polish law (e.g. `UoP`, `umowa zlecenie`, `umowa o dzieło`, `B2B`, `grosz`, `ryczałt`, `ZUS`, `Fundusz Pracy`, `FGŚP`).
2. **Translation Catalogs**:
   - Localization files located in `translations/` (e.g., `messages.pl.yaml`, `messages.en.yaml`).
3. **User-Facing Content & Templates**:
   - Localized Polish UI copy in Twig templates (`templates/**/*.twig`) when rendering the Polish locale (`/pl/...`).
4. **Localization Test Fixtures**:
   - Dedicated test cases verifying Polish parsing, grammar, or localization behavior.

---

## 2. Spec-Driven Development (SDD) Workflow

This project enforces **Spec-Driven Development (SDD)**. Formal domain specifications in the `specs/` directory serve as the machine-readable single source of truth for business logic, calculation models, and API contracts.

```
specs/
├── income-calculator.spec.json  # 2026 Polish tax rules & mathematical benchmark vectors
├── seo-audit-rules.spec.json    # Issue code catalog, severities, and trigger conditions
├── geo-readiness.spec.json      # LLM crawler bots, schema types, and GEO signals
└── mcp-tools.spec.json          # Tool signatures & schemas exposed over /mcp
```

### SDD Principles:
1. **Spec-First Invariant**: Never modify core calculation formulas, audit rule codes, or MCP tool contracts without first updating or reviewing the relevant specification in `specs/`.
2. **Deterministic Benchmark Vectors**: Specifications must include concrete input/output test vectors covering boundary conditions (e.g. Polish tax brackets, ZUS tiers).
3. **Automated Compliance Verification**: All specifications are automatically asserted against domain code via `tests/Spec/SpecificationComplianceTest.php`.

---

## 3. Code Quality, SOLID & Domain-Driven Design (DDD)

The codebase is built on **Hexagonal Architecture (Ports & Adapters)**, **Clean Architecture**, and **Domain-Driven Design (DDD)** principles. Maintain clear boundaries between layers and bounded contexts.

```
src/
├── Audit/                  # Technical SEO Audit bounded context
├── Command/                # CLI entry points (Symfony Console)
├── Controller/             # HTTP presentation layer (Web UI & JSON APIs)
├── Crawl/                  # Shared safe web retrieval bounded context
├── Geo/                    # Generative Engine Optimization analyzer context
├── Income/                 # Polish income calculator bounded context
├── Lead/                   # Contact capture bounded context
├── Market/                 # Poland used-goods price index bounded context
│   ├── Application/        # Use cases, orchestrators, contracts/interfaces
│   ├── Domain/             # Core business models, entities, value objects
│   └── Infrastructure/     # Concrete file stores, AI adapters
├── Mcp/                    # Model Context Protocol tools & schemas
└── Shared/                 # Small cross-context value objects, AI, and quotas
```

### Domain-Driven Design (DDD) Rules
- **Bounded Contexts**: Keep domain logic isolated within its respective context (`Audit`, `Crawl`, `Geo`, `Market`, `Income`, `Lead`, `Mcp`). Do not bleed context-specific logic across unrelated domains.
- **Layer Separation**:
  - **`Domain/`**: Contains pure business logic, Entities (e.g. `Product`), Aggregates (`ProductFamily`), Value Objects (`PriceObservation`), and Repository interfaces (`PriceObservationRepository`). **Zero dependencies on external frameworks, HTTP clients, or persistence mechanics.**
  - **`Application/`**: Contains use case orchestrators, DTOs, and application-level service contracts.
  - **`Infrastructure/`**: Implements domain/application contracts (for example Doctrine repositories). Handles persistence, file I/O, AI SDKs, and network transport.
  - **`Controller/` & `Command/`**: Presentation and transport adapters. Never embed core business rules directly into controllers or CLI commands.
- **Ubiquitous Language & Precision**:
  - Domain models must accurately reflect real-world semantics.
- Financial calculations must avoid floating-point rounding errors: use integer `grosz` arithmetic in Value Objects (e.g. `PriceObservation`).
- Community-submitted marketplace URLs are private review material: strip query strings and fragments, never fetch or republish them, and delete them automatically after 90 days.

### SOLID Principles
- **Single Responsibility Principle (SRP)**: Each class should have one focused responsibility. For example, keep deterministic SEO rule evaluation (`AuditRuleEngine`), crawl fetching (`HttpFetcher`), AI enrichment (`AiSummaryService`), and telemetry logging (`AuditLogger`) decoupled.
- **Open/Closed Principle (OCP)**: Design modules to be extensible via interfaces (e.g., `AiClient`, `PriceObservationRepository`) without modifying core domain algorithms.
- **Liskov Substitution Principle (LSP)**: Implementations must honor their interface contracts unconditionally without unexpected side effects.
- **Interface Segregation Principle (ISP)**: Create narrow, role-focused interfaces rather than bloated god interfaces.
- **Dependency Inversion Principle (DIP)**: High-level business logic must depend on abstractions/interfaces, not concrete infrastructure classes. Inject dependencies via constructor injection.

### Clean Code Standards
- **Strict Typing**: Always include `declare(strict_types=1);` at the top of every PHP file.
- **Explicit Types**: Use native PHP 8.5 type declarations for all method parameters, return types, and class properties. Avoid untyped `mixed` unless strictly necessary.
- **Immutability**: Prefer `readonly` properties and immutable Value Objects to prevent unintended state mutations.
- **Meaningful Naming**: Write intention-revealing, descriptive names. Code should be self-documenting; comments should explain *why*, not *what*.
- **Error Handling**: Use explicit domain exceptions (e.g. `UnsafeUrlException`) rather than generic exceptions or raw error codes.
- **Style Standard**: Follow PSR-12 code style with a 160-character line length limit (configured in `phpcs.xml.dist`).

---

## 3. Persistence, Privacy & Security Rules

1. **Private PostgreSQL Persistence**:
   - Domain repositories are implemented with Doctrine ORM and PostgreSQL 17.
   - The database must remain on the private Compose network without published host ports.
   - Schema changes require a versioned Doctrine migration committed with the code change.
   - Filesystem JSON/JSONL adapters exist only for legacy import and bounded audit logging; do not bind them as primary repositories.
2. **SSRF Guard & Network Isolation**:
   - All outbound HTTP requests must pass through multi-layered Server-Side Request Forgery defenses.
   - Reject private, reserved, and loopback IP ranges (`FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`).
   - Enforce DNS resolution pinning to prevent DNS rebinding attacks.
3. **Privacy & Anti-Scraping Protections**:
   - **No Marketplace Scraping**: Do not crawl, scrape, or store public marketplace listings, seller details, or listing URLs.
   - **URL Sanitization**: Strip query parameters from target URLs in logs and cache keys to avoid token/credential leakage.
   - **IP Anonymization**: Hash client IP addresses using HMAC-SHA256 before storing contact leads or telemetry.

---

## 4. Local Development: Docker-First Workflow

Using **Docker** is strongly recommended for all local development to ensure parity with production (PHP 8.5 Alpine, Caddy 2.10, required extensions, file permissions, and environment configurations).

### Starting the Local Environment

1. Copy the environment template:
   ```bash
   cp .env.example .env.local
   ```
2. Configure strong `APP_SECRET` and `POSTGRES_PASSWORD` values and add any optional API keys in `.env.local`.
3. Start the containers:
   ```bash
   docker compose --env-file .env.local up --build
   ```

### Executing Commands Inside the Container

Run Symfony console commands or development utilities inside the running app container:
```bash
# Clear cache
docker compose --env-file .env.local exec app php bin/console cache:clear

# Check migration status
docker compose --env-file .env.local exec app php bin/console doctrine:migrations:status

# Check container logs
docker compose --env-file .env.local logs -f app web
```

---

## 5. Testing & Verification Gate (Tests Must Be Green)

All tests, linters, and static checks **must be 100% green** before committing or deploying. Broken tests or bypassed checks are not allowed.

### 1. PHPUnit Test Suite
Run the comprehensive PHPUnit test suite:
```bash
# Via Docker test target (recommended)
docker build --target test -t bahdan-landing-test .
docker run --rm --env-file .env.example -e APP_ENV=test bahdan-landing-test vendor/bin/phpunit

# Or locally (if PHP 8.5 is installed)
vendor/bin/phpunit
```

### 2. JavaScript Test Suites
Verify all browser-side calculation, sanitization, and transpilation logic:
```bash
# Via Docker (recommended)
docker run --rm -v "$PWD:/app" -w /app node:24-alpine sh -lc 'for test in tests/js/*.test.js; do node "$test" || exit 1; done'

# Or locally (if Node.js is installed)
for test in tests/js/*.test.js; do node "$test" || exit 1; done
```

### 3. Template & Configuration Linters
Validate Twig templates and YAML translation/configuration files:
```bash
docker run --rm bahdan-landing-test php bin/console lint:twig templates
docker run --rm bahdan-landing-test php bin/console lint:yaml translations config
```

### 4. Code Style (PHP_CodeSniffer)
Check PSR-12 compliance and automatically fix violations:
```bash
# Check code style
docker run --rm bahdan-landing-test vendor/bin/phpcs
# Or locally:
composer cs:check

# Auto-fix code style issues
composer cs:fix
```

### 5. Static Analysis

Run PHPStan at level 8. The codebase achieves 0 errors with zero baseline dependencies. All changes must pass cleanly without introducing baseline files.

```bash
docker run --rm bahdan-landing-test vendor/bin/phpstan analyse --no-progress --memory-limit=512M
```

---

## 6. Pre-Commit Checklist

Before opening a pull request or pushing changes:
- [ ] All code, comments, docstrings, and commit messages are in **English** (except permitted domain terms and translation files).
- [ ] No AI-generated comments in non-English languages are present.
- [ ] `declare(strict_types=1);` is present on all new/modified PHP files.
- [ ] Code complies with PSR-12 (`composer cs:check` passes).
- [ ] Architecture layers and bounded contexts are respected (Clean Architecture / DDD).
- [ ] All automated tests pass 100% (PHPUnit and every file in `tests/js/`).
- [ ] PHPStan passes cleanly at Level 8 with 0 errors (no baseline).
- [ ] Twig and YAML configurations are valid (`lint:twig`, `lint:yaml`).
- [ ] Privacy and SSRF guard requirements are satisfied.
