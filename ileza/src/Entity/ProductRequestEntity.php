<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_requests')]
#[ORM\Index(columns: ['created_at'], name: 'idx_requests_created_at')]
class ProductRequestEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $product;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $ipHash;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $product,
        string $email,
        string $ipHash,
        \DateTimeImmutable $createdAt
    ) {
        $this->product = $product;
        $this->email = $email;
        $this->ipHash = $ipHash;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): string
    {
        return $this->product;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getIpHash(): string
    {
        return $this->ipHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
