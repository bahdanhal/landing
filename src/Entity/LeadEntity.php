<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'leads')]
#[ORM\Index(columns: ['created_at'], name: 'idx_leads_created_at')]
class LeadEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $phone;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $ipHash;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $source;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $email,
        string $phone,
        string $message,
        string $ipHash,
        string $source,
        \DateTimeImmutable $createdAt
    ) {
        $this->email = $email;
        $this->phone = $phone;
        $this->message = $message;
        $this->ipHash = $ipHash;
        $this->source = $source;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getIpHash(): string
    {
        return $this->ipHash;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
