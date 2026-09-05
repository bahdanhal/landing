<?php

declare(strict_types=1);

namespace App\Tests\Analytics;

use App\Analytics\Domain\AiInteraction;
use App\Analytics\Infrastructure\JsonlAiInteractionRepository;
use PHPUnit\Framework\TestCase;

final class JsonlAiInteractionRepositoryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/ai-telemetry-test-' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            foreach (glob($this->directory . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->directory);
        }
    }

    public function testJsonlSavesAndAggregatesAiInteractions(): void
    {
        $repository = new JsonlAiInteractionRepository($this->directory, 90);

        $now = new \DateTimeImmutable('2026-09-05T12:00:00+00:00');
        $twoDaysAgo = $now->modify('-2 days');
        $twentyDaysAgo = $now->modify('-20 days');

        $repository->save(new AiInteraction(
            $now,
            AiInteraction::TYPE_MCP_TOOL,
            'get_services_and_pricing',
            '/mcp',
            'hash-1'
        ));
        $repository->save(new AiInteraction(
            $twoDaysAgo,
            AiInteraction::TYPE_AI_CRAWLER,
            'gptbot',
            '/',
            'hash-2'
        ));
        $repository->save(new AiInteraction(
            $twentyDaysAgo,
            AiInteraction::TYPE_AI_DOCUMENT,
            '/llms.txt',
            '/llms.txt',
            'hash-3'
        ));

        $summary = $repository->summary($now);

        self::assertSame(1, $summary['last_7_days']['mcp_public_calls']);
        self::assertSame(1, $summary['last_7_days']['ai_crawler_hits']);
        self::assertSame(0, $summary['last_7_days']['ai_document_hits']);
        self::assertSame(2, $summary['last_7_days']['total_events']);
        self::assertArrayHasKey('/mcp', $summary['last_7_days']['endpoints']);
        self::assertArrayHasKey('/', $summary['last_7_days']['endpoints']);

        self::assertSame(1, $summary['last_30_days']['mcp_public_calls']);
        self::assertSame(1, $summary['last_30_days']['ai_crawler_hits']);
        self::assertSame(1, $summary['last_30_days']['ai_document_hits']);
        self::assertSame(3, $summary['last_30_days']['total_events']);
        self::assertArrayHasKey('/llms.txt', $summary['last_30_days']['endpoints']);
    }
}
