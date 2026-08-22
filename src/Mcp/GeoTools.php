<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Service\GeoAnalyzer;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class GeoTools
{
    public function __construct(private GeoAnalyzer $analyzer)
    {
    }

    #[McpTool(
        name: 'analyze_geo_readiness',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Analyze Generative Engine Optimization (GEO) signals and AI crawler readiness for a web page (schema, citations, provenance, answer structure, llms.txt, AI bot robots rules).'
    )]
    public function analyzeGeo(
        #[Schema(description: 'The web page URL to analyze for GEO readiness.')]
        string $url,
    ): string {
        try {
            $report = $this->analyzer->analyze($url);

            return $this->json([
                'url' => $report['url'] ?? $url,
                'status' => 'completed',
                'meta' => [
                    'title' => $report['meta']['title'] ?? null,
                    'word_count' => $report['meta']['word_count'] ?? 0,
                    'headings_count' => $report['meta']['headings_count'] ?? 0,
                    'schema_types' => $report['meta']['schema_types'] ?? [],
                ],
                'signals' => $report['signals'] ?? [],
                'ai_crawler_policies' => $report['ai_crawler_policies'] ?? [],
                'llms_txt' => $report['llms_txt'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'url' => $url,
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
