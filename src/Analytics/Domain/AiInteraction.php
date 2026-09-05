<?php

declare(strict_types=1);

namespace App\Analytics\Domain;

final readonly class AiInteraction
{
    public const string TYPE_MCP_TOOL = 'mcp_tool';
    public const string TYPE_AI_CRAWLER = 'ai_crawler';
    public const string TYPE_AI_DOCUMENT = 'ai_document';

    public function __construct(
        public \DateTimeImmutable $occurredAt,
        public string $type,
        public string $identifier,
        public string $path,
        public string $visitorHash,
    ) {
    }
}
