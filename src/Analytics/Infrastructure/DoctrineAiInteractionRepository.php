<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure;

use App\Analytics\Domain\AiInteraction;
use App\Analytics\Domain\AiInteractionRepository;
use App\Entity\AiInteractionEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAiInteractionRepository implements AiInteractionRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private int $retentionDays = 90,
    ) {
    }

    public function save(AiInteraction $interaction): void
    {
        $this->entityManager->getConnection()->insert('ai_interactions', [
            'occurred_at' => $interaction->occurredAt->format('Y-m-d H:i:s'),
            'type' => $interaction->type,
            'identifier' => $interaction->identifier,
            'path' => $interaction->path,
            'visitor_hash' => $interaction->visitorHash,
        ]);
    }

    /**
     * @return array{
     *     privacy: string,
     *     last_7_days: array{
     *         mcp_public_calls: int,
     *         ai_crawler_hits: int,
     *         ai_document_hits: int,
     *         total_events: int,
     *         tools: array<string, int>,
     *         bots: array<string, int>,
     *         endpoints: array<string, int>
     *     },
     *     last_30_days: array{
     *         mcp_public_calls: int,
     *         ai_crawler_hits: int,
     *         ai_document_hits: int,
     *         total_events: int,
     *         tools: array<string, int>,
     *         bots: array<string, int>,
     *         endpoints: array<string, int>
     *     },
     *     top_tools: array<string, int>,
     *     top_bots: array<string, int>,
     *     top_endpoints: array<string, int>
     * }
     */
    public function summary(\DateTimeImmutable $now): array
    {
        $thirtyDaysAgo = $now->modify('-30 days');
        $sevenDaysAgo = $now->modify('-7 days');

        $last7Days = $this->aggregatePeriod($sevenDaysAgo);
        $last30Days = $this->aggregatePeriod($thirtyDaysAgo);

        return [
            'privacy' => 'Privacy-preserving AI and MCP activity aggregates. No personal or payload data stored.',
            'last_7_days' => $last7Days,
            'last_30_days' => $last30Days,
            'top_tools' => $last30Days['tools'],
            'top_bots' => $last30Days['bots'],
            'top_endpoints' => $last30Days['endpoints'],
        ];
    }

    /**
     * @return array{
     *     mcp_public_calls: int,
     *     ai_crawler_hits: int,
     *     ai_document_hits: int,
     *     total_events: int,
     *     tools: array<string, int>,
     *     bots: array<string, int>,
     *     endpoints: array<string, int>
     * }
     */
    private function aggregatePeriod(\DateTimeImmutable $since): array
    {
        /** @var list<array{type: string, cnt: mixed}> $typeCounts */
        $typeCounts = $this->entityManager->createQueryBuilder()
            ->select('e.type', 'COUNT(e.id) as cnt')
            ->from(AiInteractionEntity::class, 'e')
            ->where('e.occurredAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('e.type')
            ->getQuery()
            ->getResult();

        $mcpCalls = 0;
        $crawlerHits = 0;
        $documentHits = 0;
        $totalEvents = 0;

        foreach ($typeCounts as $row) {
            $count = (int) $row['cnt'];
            $totalEvents += $count;
            match ($row['type']) {
                AiInteraction::TYPE_MCP_TOOL => $mcpCalls = $count,
                AiInteraction::TYPE_AI_CRAWLER => $crawlerHits = $count,
                AiInteraction::TYPE_AI_DOCUMENT => $documentHits = $count,
                default => null,
            };
        }

        /** @var list<array{identifier: string, cnt: mixed}> $toolsResult */
        $toolsResult = $this->entityManager->createQueryBuilder()
            ->select('e.identifier', 'COUNT(e.id) as cnt')
            ->from(AiInteractionEntity::class, 'e')
            ->where('e.occurredAt >= :since')
            ->andWhere('e.type = :type')
            ->setParameter('since', $since)
            ->setParameter('type', AiInteraction::TYPE_MCP_TOOL)
            ->groupBy('e.identifier')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $tools = [];
        foreach ($toolsResult as $row) {
            $tool = trim((string) $row['identifier']);
            if ($tool !== '') {
                $tools[$tool] = (int) $row['cnt'];
            }
        }

        /** @var list<array{identifier: string, cnt: mixed}> $botsResult */
        $botsResult = $this->entityManager->createQueryBuilder()
            ->select('e.identifier', 'COUNT(e.id) as cnt')
            ->from(AiInteractionEntity::class, 'e')
            ->where('e.occurredAt >= :since')
            ->andWhere('e.type = :type')
            ->setParameter('since', $since)
            ->setParameter('type', AiInteraction::TYPE_AI_CRAWLER)
            ->groupBy('e.identifier')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $bots = [];
        foreach ($botsResult as $row) {
            $bot = trim((string) $row['identifier']);
            if ($bot !== '') {
                $bots[$bot] = (int) $row['cnt'];
            }
        }

        /** @var list<array{path: string, cnt: mixed}> $endpointsResult */
        $endpointsResult = $this->entityManager->createQueryBuilder()
            ->select('e.path', 'COUNT(e.id) as cnt')
            ->from(AiInteractionEntity::class, 'e')
            ->where('e.occurredAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('e.path')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $endpoints = [];
        foreach ($endpointsResult as $row) {
            $path = trim((string) $row['path']);
            if ($path !== '') {
                $endpoints[$path] = (int) $row['cnt'];
            }
        }

        return [
            'mcp_public_calls' => $mcpCalls,
            'ai_crawler_hits' => $crawlerHits,
            'ai_document_hits' => $documentHits,
            'total_events' => $totalEvents,
            'tools' => $tools,
            'bots' => $bots,
            'endpoints' => $endpoints,
        ];
    }

    public function prune(\DateTimeImmutable $now): int
    {
        $cutoff = $now->modify(sprintf('-%d days', max(1, $this->retentionDays)));

        return (int) $this->entityManager->createQueryBuilder()
            ->delete(AiInteractionEntity::class, 'e')
            ->where('e.occurredAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
