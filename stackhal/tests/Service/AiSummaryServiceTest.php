<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Audit\Application\AiSummaryService;
use App\Shared\AI\AiClient;
use App\Shared\AI\AiUseCase;
use PHPUnit\Framework\TestCase;

final class AiSummaryServiceTest extends TestCase
{
    public function testIsSilentWhenNotConfigured(): void
    {
        $ai = new class implements AiClient {
            public function complete(string $systemPrompt, string $userPrompt, AiUseCase $useCase): string
            {
                throw new \RuntimeException('not configured');
            }
        };
        $service = new AiSummaryService($ai, 'test');

        self::assertNull($service->summarize([
            'target' => 'https://example.com/',
            'score' => 100,
            'counts' => [],
            'summary' => [],
            'issues' => [],
        ]));
    }

    public function testParsesAValidStructuredSummary(): void
    {
        $ai = new class implements AiClient {
            public function complete(string $systemPrompt, string $userPrompt, AiUseCase $useCase): string
            {
                // phpcs:ignore Generic.Files.LineLength
                return '{"overview":"Fix canonicalization first.","priorities":[{"title":"Canonicals","why":"Duplicates split signals.","action":"Add self-referencing canonicals."}]}';
            }
        };
        $service = new AiSummaryService($ai, 'test-model');
        $summary = $service->summarize([
            'target' => 'https://example.com/',
            'score' => 50,
            'counts' => ['critical' => 1, 'warning' => 0, 'info' => 0],
            'summary' => [],
            'issues' => [],
        ]);

        self::assertNotNull($summary);
        self::assertSame('Fix canonicalization first.', $summary['overview']);
        self::assertSame('Canonicals', $summary['priorities'][0]['title']);
    }
}
