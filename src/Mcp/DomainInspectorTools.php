<?php

declare(strict_types=1);

namespace App\Mcp;

use App\DomainInspector\Application\DomainInspector;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class DomainInspectorTools
{
    public function __construct(private DomainInspector $inspector)
    {
    }

    #[McpTool(
        name: 'inspect_domain_security',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Inspect domain email security and deliverability standards: DMARC (BIMI compliance), BIMI DNS & SVG reachability, MTA-STS (RFC 8461), SMTP TLS-RPT (RFC 8460), SPF and MX records.'
    )]
    public function inspectDomain(
        #[Schema(description: 'The domain name to inspect (e.g. stripe.com, example.com).')]
        string $domain,
    ): string {
        try {
            $report = $this->inspector->inspect($domain);

            return $this->json([
                'status' => 'completed',
                'report' => $report->toArray(),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'domain' => $domain,
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
