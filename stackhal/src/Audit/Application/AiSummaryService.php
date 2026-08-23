<?php

declare(strict_types=1);

namespace App\Audit\Application;

use App\Shared\AI\AiClient;
use App\Shared\AI\AiUseCase;

final readonly class AiSummaryService
{
    public function __construct(
        private AiClient $ai,
        private string $configurationFingerprint,
    ) {
    }

    /**
     * @param array<string, mixed> $report
     * @return array{overview:string,priorities:list<array{title:string,why:string,action:string}>}|null
     */
    public function summarize(array $report): ?array
    {
        $evidence = [
            'target' => $report['target'],
            'score' => $report['score'],
            'counts' => $report['counts'],
            'crawl_summary' => $report['summary'],
            'findings' => array_slice(array_map(static fn (array $issue) => [
                'severity' => $issue['severity'],
                'code' => $issue['code'],
                'detail' => $issue['detail'],
            ], $report['issues']), 0, 24),
        ];

        try {
            $text = $this->ai->complete(
            // phpcs:ignore Generic.Files.LineLength
                'You are a technical SEO analyst. Use only the supplied deterministic evidence. Return strict JSON with overview (2-3 concise sentences) and priorities (up to 5 objects with title, why, action). Do not use markdown or invent facts. The seo_audit_probe query is a synthetic test proving that arbitrary unknown query strings are accepted; never describe it as a real site feature or recommend blocking that probe name specifically.',
                json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                AiUseCase::Summary,
            );
            $cleanText = preg_replace('/^```(?:json)?|```$/m', '', trim($text));
            $text = trim($cleanText ?? '');
            $summary = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($summary) || !is_string($summary['overview'] ?? null) || !is_array($summary['priorities'] ?? null)) {
                return null;
            }
            $priorities = [];
            foreach (array_slice($summary['priorities'], 0, 5) as $priority) {
                if (
                    is_array($priority)
                    && is_string($priority['title'] ?? null)
                    && is_string($priority['why'] ?? null)
                    && is_string($priority['action'] ?? null)
                ) {
                    $priorities[] = [
                        'title' => $priority['title'],
                        'why' => $priority['why'],
                        'action' => $priority['action'],
                    ];
                }
            }

            return [
                'overview' => $summary['overview'],
                'priorities' => $priorities,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function cacheVariant(): string
    {
        return $this->configurationFingerprint;
    }
}
