<?php

declare(strict_types=1);

namespace App\Analytics\Domain;

interface AiInteractionRepository
{
    public function save(AiInteraction $interaction): void;

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
    public function summary(\DateTimeImmutable $now): array;

    public function prune(\DateTimeImmutable $now): int;
}
