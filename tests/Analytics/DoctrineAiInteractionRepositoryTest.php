<?php

declare(strict_types=1);

namespace App\Tests\Analytics;

use App\Analytics\Domain\AiInteraction;
use App\Analytics\Infrastructure\DoctrineAiInteractionRepository;
use App\Tests\DoctrineTestCase;

final class DoctrineAiInteractionRepositoryTest extends DoctrineTestCase
{
    public function testSavesAndAggregatesAiInteractions(): void
    {
        $repository = new DoctrineAiInteractionRepository($this->entityManager, 90);

        $now = new \DateTimeImmutable('2026-09-05T12:00:00+00:00');
        $twoDaysAgo = $now->modify('-2 days');
        $twentyDaysAgo = $now->modify('-20 days');
        $oneHundredDaysAgo = $now->modify('-100 days');

        // MCP tool calls
        $repository->save(new AiInteraction(
            $now,
            AiInteraction::TYPE_MCP_TOOL,
            'get_services_and_pricing',
            '/mcp',
            'hash-1'
        ));
        $repository->save(new AiInteraction(
            $twoDaysAgo,
            AiInteraction::TYPE_MCP_TOOL,
            'get_portfolio_overview',
            '/mcp',
            'hash-2'
        ));
        $repository->save(new AiInteraction(
            $twentyDaysAgo,
            AiInteraction::TYPE_MCP_TOOL,
            'get_portfolio_overview',
            '/mcp',
            'hash-3'
        ));

        // AI Crawlers
        $repository->save(new AiInteraction(
            $now,
            AiInteraction::TYPE_AI_CRAWLER,
            'gptbot',
            '/',
            'hash-4'
        ));
        $repository->save(new AiInteraction(
            $twoDaysAgo,
            AiInteraction::TYPE_AI_CRAWLER,
            'claudebot',
            '/resume',
            'hash-5'
        ));

        // AI Documents
        $repository->save(new AiInteraction(
            $now,
            AiInteraction::TYPE_AI_DOCUMENT,
            '/llms.txt',
            '/llms.txt',
            'hash-6'
        ));

        // Expired interaction
        $repository->save(new AiInteraction(
            $oneHundredDaysAgo,
            AiInteraction::TYPE_MCP_TOOL,
            'get_portfolio_overview',
            '/mcp',
            'hash-old'
        ));

        $summary = $repository->summary($now);

        // 7 days check
        self::assertSame(2, $summary['last_7_days']['mcp_public_calls']);
        self::assertSame(2, $summary['last_7_days']['ai_crawler_hits']);
        self::assertSame(1, $summary['last_7_days']['ai_document_hits']);
        self::assertSame(5, $summary['last_7_days']['total_events']);
        self::assertArrayHasKey('get_services_and_pricing', $summary['last_7_days']['tools']);
        self::assertArrayHasKey('get_portfolio_overview', $summary['last_7_days']['tools']);
        self::assertArrayHasKey('gptbot', $summary['last_7_days']['bots']);
        self::assertArrayHasKey('claudebot', $summary['last_7_days']['bots']);
        self::assertArrayHasKey('/mcp', $summary['last_7_days']['endpoints']);
        self::assertArrayHasKey('/', $summary['last_7_days']['endpoints']);
        self::assertArrayHasKey('/resume', $summary['last_7_days']['endpoints']);
        self::assertArrayHasKey('/llms.txt', $summary['last_7_days']['endpoints']);

        // 30 days check
        self::assertSame(3, $summary['last_30_days']['mcp_public_calls']);
        self::assertSame(2, $summary['last_30_days']['ai_crawler_hits']);
        self::assertSame(1, $summary['last_30_days']['ai_document_hits']);
        self::assertSame(6, $summary['last_30_days']['total_events']);
        self::assertSame(2, $summary['last_30_days']['tools']['get_portfolio_overview']);
        self::assertSame(1, $summary['last_30_days']['tools']['get_services_and_pricing']);

        // Prune check
        $pruned = $repository->prune($now);
        self::assertSame(1, $pruned);
    }
}
