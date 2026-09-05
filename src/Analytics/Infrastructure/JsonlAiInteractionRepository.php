<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure;

use App\Analytics\Domain\AiInteraction;
use App\Analytics\Domain\AiInteractionRepository;

final readonly class JsonlAiInteractionRepository implements AiInteractionRepository
{
    public function __construct(
        private string $directory,
        private int $retentionDays = 90,
    ) {
    }

    public function save(AiInteraction $interaction): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }

        $filename = sprintf(
            '%s/ai_interactions-%s.jsonl',
            rtrim($this->directory, '/'),
            $interaction->occurredAt->format('Y-m')
        );

        $line = json_encode([
            'occurred_at' => $interaction->occurredAt->format(DATE_ATOM),
            'type' => $interaction->type,
            'identifier' => $interaction->identifier,
            'path' => $interaction->path,
            'visitor_hash' => $interaction->visitorHash,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";

        @file_put_contents($filename, $line, FILE_APPEND | LOCK_EX);
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

        $events = $this->readEvents($thirtyDaysAgo);

        $last7Events = array_values(array_filter(
            $events,
            static fn (array $e): bool => new \DateTimeImmutable((string) $e['occurred_at']) >= $sevenDaysAgo
        ));

        $last7 = $this->aggregateEvents($last7Events);
        $last30 = $this->aggregateEvents($events);

        return [
            'privacy' => 'Privacy-preserving AI and MCP activity aggregates. No personal or payload data stored.',
            'last_7_days' => $last7,
            'last_30_days' => $last30,
            'top_tools' => $last30['tools'],
            'top_bots' => $last30['bots'],
            'top_endpoints' => $last30['endpoints'],
        ];
    }

    /**
     * @param list<array{occurred_at: string, type: string, identifier: string, path: string, visitor_hash: string}> $events
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
    private function aggregateEvents(array $events): array
    {
        $mcpCalls = 0;
        $crawlerHits = 0;
        $documentHits = 0;
        $tools = [];
        $bots = [];
        $endpoints = [];

        foreach ($events as $event) {
            $type = $event['type'];
            $ident = $event['identifier'];
            $path = $event['path'];

            match ($type) {
                AiInteraction::TYPE_MCP_TOOL => $mcpCalls++,
                AiInteraction::TYPE_AI_CRAWLER => $crawlerHits++,
                AiInteraction::TYPE_AI_DOCUMENT => $documentHits++,
                default => null,
            };

            if ($type === AiInteraction::TYPE_MCP_TOOL && $ident !== '') {
                $tools[$ident] = ($tools[$ident] ?? 0) + 1;
            } elseif ($type === AiInteraction::TYPE_AI_CRAWLER && $ident !== '') {
                $bots[$ident] = ($bots[$ident] ?? 0) + 1;
            }

            if ($path !== '') {
                $endpoints[$path] = ($endpoints[$path] ?? 0) + 1;
            }
        }

        arsort($tools);
        arsort($bots);
        arsort($endpoints);

        return [
            'mcp_public_calls' => $mcpCalls,
            'ai_crawler_hits' => $crawlerHits,
            'ai_document_hits' => $documentHits,
            'total_events' => count($events),
            'tools' => array_slice($tools, 0, 10, true),
            'bots' => array_slice($bots, 0, 10, true),
            'endpoints' => array_slice($endpoints, 0, 10, true),
        ];
    }

    /**
     * @return list<array{occurred_at: string, type: string, identifier: string, path: string, visitor_hash: string}>
     */
    private function readEvents(\DateTimeImmutable $since): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $files = glob(rtrim($this->directory, '/') . '/ai_interactions-*.jsonl') ?: [];
        $events = [];

        foreach ($files as $file) {
            $handle = @fopen($file, 'r');
            if ($handle === false) {
                continue;
            }

            while (($raw = fgets($handle)) !== false) {
                $raw = trim($raw);
                if ($raw === '') {
                    continue;
                }

                try {
                    /** @var array{occurred_at: string, type: string, identifier: string, path: string, visitor_hash: string} $data */
                    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    $date = new \DateTimeImmutable($data['occurred_at']);
                    if ($date >= $since) {
                        $events[] = $data;
                    }
                } catch (\Throwable) {
                    // Ignore corrupted lines
                }
            }

            fclose($handle);
        }

        return $events;
    }

    public function prune(\DateTimeImmutable $now): int
    {
        if (!is_dir($this->directory)) {
            return 0;
        }

        $cutoff = $now->modify(sprintf('-%d days', max(1, $this->retentionDays)));
        $files = glob(rtrim($this->directory, '/') . '/ai_interactions-*.jsonl') ?: [];
        $pruned = 0;

        foreach ($files as $file) {
            if (preg_match('/ai_interactions-(\d{4}-\d{2})\.jsonl$/', $file, $matches)) {
                $fileMonth = new \DateTimeImmutable($matches[1] . '-01 00:00:00');
                if ($fileMonth->modify('+1 month') < $cutoff) {
                    @unlink($file);
                    $pruned++;
                }
            }
        }

        return $pruned;
    }
}
