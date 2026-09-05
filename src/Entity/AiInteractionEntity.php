<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ai_interactions')]
#[ORM\Index(columns: ['occurred_at'], name: 'idx_ai_interactions_occurred_at')]
#[ORM\Index(columns: ['occurred_at', 'type'], name: 'idx_ai_interactions_occurred_type')]
#[ORM\Index(columns: ['occurred_at', 'identifier'], name: 'idx_ai_interactions_occurred_ident')]
#[ORM\Index(columns: ['occurred_at', 'path'], name: 'idx_ai_interactions_occurred_path')]
class AiInteractionEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $type;

    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $identifier;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $path;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $visitorHash;

    public function __construct(
        \DateTimeImmutable $occurredAt,
        string $type,
        string $identifier,
        string $path,
        string $visitorHash
    ) {
        $this->occurredAt = $occurredAt;
        $this->type = $type;
        $this->identifier = $identifier;
        $this->path = $path;
        $this->visitorHash = $visitorHash;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getVisitorHash(): string
    {
        return $this->visitorHash;
    }
}
