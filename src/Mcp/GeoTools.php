<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Geo\Application\GeoAnalyzer;
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
                'target' => $report['target'] ?? $url,
                'final_url' => $report['final_url'] ?? $url,
                'status' => 'completed',
                'score' => $report['score'] ?? 0,
                'counts' => $report['counts'] ?? [],
                'page' => $report['page'] ?? [],
                'checks' => $report['checks'] ?? [],
                'crawler_controls' => $report['crawler_controls'] ?? [],
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
