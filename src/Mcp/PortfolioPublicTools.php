<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Lead\Application\CaptureLead;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class PortfolioPublicTools
{
    public function __construct(
        private CaptureLead $captureLead,
    ) {
    }

    #[McpTool(
        name: 'get_portfolio_overview',
        description: "Get an overview of Bahdan Hal's software engineering consulting services, technical stack, and ecosystem projects."
    )]
    public function overview(): string
    {
        return json_encode([
            'engineer' => 'Bahdan Hal',
            'role' => 'Senior Backend & Systems Engineer / Software Architect',
            'pricing' => [
                'standard_contract_rate' => '$35/hour',
                'promotional_discount_rate' => '$30/hour',
                'long_term_cooperation_rate' => '$25/hour',
                'currency' => 'USD',
                'details_tool' => 'get_services_and_pricing',
            ],
            'specialization' => [
                'Distributed backend systems and high-throughput microservices',
                'PHP 8.5+ / Symfony 7 & 8 architecture, Go microservices, Python tools',
                'Modern infrastructure with Caddy 2.10, Docker, and PostgreSQL 17',
                'Protocol engineering (Model Context Protocol / MCP, FastCGI, SSE, HTTP/3)',
            ],
            'ecosystem_projects' => [
                [
                    'name' => 'Bahdan Hal Portfolio & Consulting',
                    'domain' => 'https://bahdanhal.pl',
                    'description' => 'Engineering portfolio, technical advisory, and client consultation intake.',
                ],
                [
                    'name' => 'IleZa.pl',
                    'domain' => 'https://ileza.pl',
                    'description' => 'Polish used electronics price radar and net income/tax calculator.',
                ],
                [
                    'name' => 'StackHal',
                    'domain' => 'https://stackhal.com',
                    'description' => 'Developer utilities (Caddyfile transpiler, Apple PKPass inspector, CIDR matrix, domain & SEO audits).',
                ],
            ],
            'public_tools' => [
                'get_portfolio_overview' => 'High-level overview of Bahdan Hal and ecosystem projects',
                'get_services_and_pricing' => 'Commercial services catalog, pricing rates ($30-$35/hr contracts, $25/hr long-term), and contract terms',
                'get_cv_and_skills' => 'Complete CV, employment history, technical skill breakdown, and languages',
                'submit_contact_lead' => 'Submit direct engineering consultation requests or inquiries',
            ],
            'contact' => [
                'website' => 'https://bahdanhal.pl',
                'mcp_endpoint' => 'https://bahdanhal.pl/mcp',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    #[McpTool(
        name: 'get_services_and_pricing',
        // phpcs:ignore Generic.Files.LineLength
        description: "Get Bahdan Hal's commercial engineering services catalog with transparent hourly pricing and engagement terms."
    )]
    public function servicesAndPricing(): string
    {
        return json_encode([
            'engineer' => 'Bahdan Hal',
            'rates' => [
                'standard_contract_rate' => '$35/hour',
                'promotional_discount_rate' => '$30/hour',
                'long_term_cooperation_rate' => '$25/hour',
                'currency' => 'USD',
                'note' => 'Current promotional discount rate of $30/hour applies to standard contract engagements. '
                    . 'Ongoing, long-term partnerships qualify for $25/hour.',
            ],
            'engagement_terms' => [
                'contract_model' => 'B2B contract with EU/international invoicing',
                'billing_options' => 'Hourly tracking or milestone-based fixed scope',
                'location_and_availability' => 'Gdańsk, Poland (UTC+1 / UTC+2) · Remote / Hybrid',
                'minimum_commitment' => 'Flexible: single audit/sprint to multi-month engagements',
            ],
            'services' => [
                [
                    'id' => 'turnkey_websites',
                    'title' => 'Turnkey Websites & High-Performance Landing Pages',
                    'description' => 'Custom-crafted, ultra-fast bilingual websites with semantic HTML/CSS, '
                        . 'robust backend, automated SSL, and 95-100 Google Lighthouse scores.',
                    'deliverables' => [
                        'Mobile-first responsive design',
                        'SEO meta & structured JSON-LD data',
                        'Fast contact forms with spam protection',
                        'Caddy/Nginx deployment and HTTPS',
                        'Google Search Console indexing setup',
                    ],
                    'ideal_for' => 'B2B companies, consulting firms, agencies, and service businesses needing a trustworthy online presence.',
                ],
                [
                    'id' => 'ecommerce_stores',
                    'title' => 'E-Commerce & Online Stores',
                    'description' => 'High-converting online shops and product portals with seamless integrations '
                        . 'for Polish and global payment gateways, shipping couriers, and automatic invoicing.',
                    'deliverables' => [
                        'Product catalogs & faceted search',
                        'Cart & checkout workflows',
                        'PayU, BLIK, Przelewy24, Stripe integrations',
                        'InPost Paczkomaty and shipping APIs',
                        'Automated email receipts and sales dashboard',
                    ],
                    'ideal_for' => 'Brands and retailers launching or migrating direct-to-consumer (D2C) or B2B sales channels.',
                ],
                [
                    'id' => 'client_portals_custom_apps',
                    'title' => 'Client Portals, Dashboards & Custom Web Apps',
                    'description' => 'Bespoke web applications, customer self-service portals, '
                        . 'and secure role-based admin panels tailored precisely to company workflows.',
                    'deliverables' => [
                        'User accounts & granular permission roles',
                        'Interactive charts and KPI reporting',
                        'PDF invoice and report generation',
                        'Data import/export pipelines',
                        'Background queue and async worker processing',
                    ],
                    'ideal_for' => 'Growing companies looking to digitize proprietary business processes and eliminate spreadsheet chaos.',
                ],
                [
                    'id' => 'ai_and_mcp_integrations',
                    'title' => 'AI Integrations & Custom MCP Tools',
                    'description' => 'Embedding modern LLMs (Anthropic Claude, OpenAI, Google Gemini) and Model Context '
                        . 'Protocol (MCP) servers directly into software for intelligent automation.',
                    'deliverables' => [
                        'Custom AI assistant tooling and agents',
                        'Automated document & PDF parsing',
                        'Intelligent semantic search over internal knowledge bases',
                        'Production-grade MCP server endpoints for database access',
                    ],
                    'ideal_for' => 'Businesses wanting to harness AI productively without bloated generic subscriptions.',
                ],
                [
                    'id' => 'process_automation',
                    'title' => 'Process Automation & API Integrations',
                    'description' => 'Robust background data synchronization pipelines, webhook handlers, '
                        . 'and automated workflows keeping systems in sync 24/7.',
                    'deliverables' => [
                        'REST and GraphQL API bridges',
                        'CRM synchronization (HubSpot, Pipedrive)',
                        'Automated email/SMS notification pipelines',
                        'Custom scraping and market observation systems',
                    ],
                    'ideal_for' => 'Companies wanting seamless data flow between third-party systems with zero manual copying.',
                ],
                [
                    'id' => 'technical_audits_rescue',
                    'title' => 'Technical Audits, Speed Optimization & Production Rescue',
                    'description' => 'Comprehensive technical audits, database query tuning, Linux/Docker security '
                        . 'hardening, PHP version upgrades, and emergency production defect resolution.',
                    'deliverables' => [
                        'Actionable audit report with priority checklist',
                        'SQL query tuning and index optimization',
                        'PHP 8.x modernization and refactoring',
                        'Automated daily backup and recovery procedures',
                        'DMARC, SPF, and DKIM email deliverability fixes',
                    ],
                    'ideal_for' => 'Existing web applications needing stability, speed improvements, or a safe modernization roadmap.',
                ],
            ],
            'contact' => [
                'website_contact_form' => 'https://bahdanhal.pl/#contact',
                'submit_lead_mcp_tool' => 'submit_contact_lead',
                'email' => 'bahdan.hal@hotmail.com',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    #[McpTool(
        name: 'get_cv_and_skills',
        // phpcs:ignore Generic.Files.LineLength
        description: "Get Bahdan Hal's full curriculum vitae (CV), including professional experience, technical skill stack, education, and language proficiencies."
    )]
    public function cvAndSkills(): string
    {
        return json_encode([
            'engineer' => 'Bahdan Hal',
            'role' => 'Senior Backend & Systems Engineer / Software Architect',
            'location' => 'Gdańsk, Poland (Open to Remote / Hybrid)',
            'summary' => 'Senior Backend and Systems Engineer with 5+ years of production experience '
                . 'designing high-throughput web systems, distributed microservices, and practical AI integrations. '
                . 'Modernizes legacy platforms, architects robust database schemas, and builds hardened Linux/Docker '
                . 'infrastructure. Strong advocate for clean architecture, pragmatic engineering, and test-driven reliability.',
            'experience' => [
                [
                    'company' => 'Freedom Mobile',
                    'role' => 'Backend & Systems Engineer',
                    'period' => '2026 - Present',
                    'location' => 'Remote',
                    'highlights' => [
                        'Designed and implemented backend services and internal AI-assisted agent tooling for a telecommunications platform.',
                        'Built robust third-party API integration layers with resilient webhook processing and retry mechanisms.',
                        'Investigated and resolved complex production defects, reducing incident recovery time and improving platform stability.',
                    ],
                ],
                [
                    'company' => 'Gdańskie Wydawnictwo Oświatowe (GWO)',
                    'role' => 'Full-Stack & Backend Developer',
                    'period' => '2022 - 2026',
                    'location' => 'Gdańsk, Poland',
                    'highlights' => [
                        'Maintained and modernized high-traffic educational platforms (Symfony, Laravel) serving over 2 million active students and teachers.',
                        'Refactored legacy monolith subsystems into modular architectures, '
                            . 'optimizing complex SQL queries and caching to cut response times by up to 70%.',
                        'Designed editorial content tools and integrated generative and computer vision AI models into automated publishing workflows.',
                        'Established automated CI/CD quality gates, static analysis pipelines (PHPStan Level 8), and unit/functional test suites.',
                    ],
                ],
                [
                    'company' => 'Independent Software Consulting',
                    'role' => 'Software Architect & Consultant',
                    'period' => '2022 - Present',
                    'location' => 'Global / Remote',
                    'highlights' => [
                        'Delivered 28+ international projects on Upwork with a 100% Job Success score and Top Rated status.',
                        'Executed zero-downtime server migrations, Linux security hardening, database index tuning, and SMTP deliverability fixes.',
                        'Developed custom web applications, e-commerce integrations, and Model Context Protocol (MCP) server endpoints.',
                    ],
                ],
            ],
            'skills' => [
                'backend_and_languages' => [
                    'PHP 8.2-8.5',
                    'Symfony 6/7/8',
                    'Laravel 10/11',
                    'Go',
                    'Python',
                    'TypeScript',
                    'Node.js',
                    'REST APIs',
                    'GraphQL',
                    'Model Context Protocol (MCP)',
                ],
                'databases_and_caching' => [
                    'PostgreSQL 14-17',
                    'MySQL / MariaDB',
                    'Redis',
                    'SQLite',
                    'Doctrine ORM',
                    'Eloquent',
                    'SQL & Index Optimization',
                ],
                'infrastructure_and_devops' => [
                    'Docker',
                    'Docker Compose',
                    'Linux (Debian, Ubuntu)',
                    'Caddy 2',
                    'Nginx',
                    'CI/CD (GitHub Actions)',
                    'Bash',
                    'SSH Hardening',
                ],
                'architecture_and_quality' => [
                    'Clean Architecture',
                    'Domain-Driven Design (DDD)',
                    'Event-Driven Design',
                    'PHPUnit',
                    'PHPStan Level 8',
                    'PHP-CS-Fixer / PHPCS',
                    'Microservices',
                ],
                'ai_and_modern_tooling' => [
                    'OpenAI API',
                    'Anthropic Claude API',
                    'Google Gemini API',
                    'Model Context Protocol (MCP)',
                    'Vector Embeddings',
                    'Web Scraping',
                    'Git',
                ],
            ],
            'education' => 'Higher Technical Education in Information Technology & Software Engineering',
            'languages' => [
                ['language' => 'English', 'level' => 'Professional proficiency'],
                ['language' => 'Polish', 'level' => 'Fluent'],
                ['language' => 'Belarusian', 'level' => 'Native'],
                ['language' => 'Russian', 'level' => 'Native'],
            ],
            'reputation' => [
                'upwork_status' => 'Top Rated',
                'job_success_score' => '100%',
                'completed_contracts' => '28+ international contracts',
                'rating' => '5.0 ★ rating',
            ],
            'contact' => [
                'website' => 'https://bahdanhal.pl/resume',
                'pdf_resume' => 'https://bahdanhal.pl/resume.pdf',
                'submit_lead_tool' => 'submit_contact_lead',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    #[McpTool(
        name: 'submit_contact_lead',
        description: 'Submit an engineering consultation request or business inquiry directly to Bahdan.'
    )]
    public function submitLead(
        #[Schema(description: 'Your email address for receiving a reply.')] string $email = '',
        #[Schema(description: 'Optional phone number (international format).')] string $phone = '',
        #[Schema(description: 'Detailed inquiry message describing your project or requirements.')] string $message = '',
    ): string {
        $cleanEmail = strtolower(trim($email));
        $cleanPhone = trim($phone);
        $cleanMessage = trim($message);

        if ($cleanEmail === '' && $cleanPhone === '') {
            return json_encode([
                'success' => false,
                'error' => 'Please provide at least an email address or a phone number.',
            ], JSON_THROW_ON_ERROR);
        }

        if ($cleanEmail !== '' && filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) === false) {
            return json_encode([
                'success' => false,
                'error' => 'Invalid email address format.',
            ], JSON_THROW_ON_ERROR);
        }

        if ($cleanPhone !== '' && !preg_match('/^\+?[0-9 ()-]{7,30}$/', $cleanPhone)) {
            return json_encode([
                'success' => false,
                'error' => 'Invalid phone number format. Use international format (e.g. +48...)',
            ], JSON_THROW_ON_ERROR);
        }

        if ($cleanMessage === '') {
            return json_encode([
                'success' => false,
                'error' => 'Message cannot be empty.',
            ], JSON_THROW_ON_ERROR);
        }

        try {
            $this->captureLead->execute(
                $cleanEmail,
                $cleanPhone,
                $cleanMessage,
                'mcp-client',
                'mcp',
            );

            return json_encode([
                'success' => true,
                'message' => 'Thank you! Your inquiry has been recorded. Bahdan will review and get back to you.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return json_encode([
                'success' => false,
                'error' => 'Failed to save inquiry: ' . $e->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }
    }
}
