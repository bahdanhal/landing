<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'price_tips')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_tips_expires_at')]
#[ORM\Index(columns: ['submitted_at'], name: 'idx_tips_submitted_at')]
class PriceTipEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $productSlug;

    #[ORM\Column(type: Types::STRING, length: 2048)]
    private string $listingUrl;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $ipHash;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $submittedAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    public function __construct(
        string $productSlug,
        string $listingUrl,
        string $email,
        string $ipHash,
        \DateTimeImmutable $submittedAt,
        \DateTimeImmutable $expiresAt
    ) {
        $this->productSlug = $productSlug;
        $this->listingUrl = $listingUrl;
        $this->email = $email;
        $this->ipHash = $ipHash;
        $this->submittedAt = $submittedAt;
        $this->expiresAt = $expiresAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductSlug(): string
    {
        return $this->productSlug;
    }

    public function getListingUrl(): string
    {
        return $this->listingUrl;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getIpHash(): string
    {
        return $this->ipHash;
    }

    public function getSubmittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
