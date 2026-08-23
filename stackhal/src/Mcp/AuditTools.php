<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Audit\Application\SiteAuditor;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class AuditTools
{
    public function __construct(private SiteAuditor $auditor)
    {
    }

    #[McpTool(
        name: 'audit_website_seo',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Run a deterministic technical SEO audit for a public website URL. Checks canonicals, title tags, headings, robots.txt, sitemaps, redirects, crawl traps, and indexability.'
    )]
    public function auditWebsite(
        #[Schema(description: 'The public website URL to audit (e.g. https://example.com).')]
        string $url,
    ): string {
        try {
            $report = $this->auditor->audit($url);

            return $this->json([
                'target' => $report['target'] ?? $url,
                'status' => 'completed',
                'summary' => [
                    'pages_crawled' => $report['summary']['pages_crawled'] ?? 0,
                    'critical_issues' => $report['summary']['critical'] ?? 0,
                    'warnings' => $report['summary']['warning'] ?? 0,
                    'info_items' => $report['summary']['info'] ?? 0,
                ],
                'issues' => $report['grouped_issues'] ?? $report['issues'] ?? [],
                'redirect_matrix' => $report['redirect_matrix'] ?? [],
                'robots_summary' => $report['robots']['status'] ?? null,
                'sitemap_summary' => [
                    'url_count' => count($report['sitemap']['urls'] ?? []),
                    'error_count' => count($report['sitemap']['errors'] ?? []),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'target' => $url,
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
