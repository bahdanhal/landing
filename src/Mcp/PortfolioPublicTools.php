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
            'specialization' => [
                'Distributed backend systems and high-throughput microservices',
                'PHP 8.5+ / Symfony 7 architecture, Go microservices, Python tools',
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
            'contact' => [
                'website' => 'https://bahdanhal.pl',
                'mcp_endpoint' => 'https://bahdanhal.pl/mcp',
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
