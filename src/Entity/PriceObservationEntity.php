<?php

declare(strict_types=1);

namespace App\Entity;

use App\Market\Domain\PriceObservation;
use App\Shared\Domain\Grosz;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'price_observations')]
#[ORM\UniqueConstraint(name: 'uniq_product_observed_at', columns: ['product_slug', 'observed_at'])]
#[ORM\Index(columns: ['product_slug'], name: 'idx_observations_product_slug')]
class PriceObservationEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $productSlug;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $observedAt;

    #[ORM\Column(type: 'grosz')]
    private Grosz|int $medianGrosz;

    #[ORM\Column(type: 'grosz')]
    private Grosz|int $lowGrosz;

    #[ORM\Column(type: 'grosz')]
    private Grosz|int $highGrosz;

    #[ORM\Column(type: Types::INTEGER)]
    private int $sampleSize;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $confidence;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $methodology;

    public function __construct(
        string $productSlug,
        \DateTimeImmutable $observedAt,
        Grosz|int $medianGrosz,
        Grosz|int $lowGrosz,
        Grosz|int $highGrosz,
        int $sampleSize,
        string $confidence,
        ?string $summary,
        string $methodology
    ) {
        $this->productSlug = $productSlug;
        $this->observedAt = $observedAt;
        $this->medianGrosz = $medianGrosz;
        $this->lowGrosz = $lowGrosz;
        $this->highGrosz = $highGrosz;
        $this->sampleSize = $sampleSize;
        $this->confidence = $confidence;
        $this->summary = $summary;
        $this->methodology = $methodology;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductSlug(): string
    {
        return $this->productSlug;
    }

    public function getObservedAt(): \DateTimeImmutable
    {
        return $this->observedAt;
    }

    public function getMedian(): Grosz
    {
        return $this->medianGrosz instanceof Grosz ? $this->medianGrosz : Grosz::fromGrosz($this->medianGrosz);
    }

    public function getMedianGrosz(): int
    {
        return $this->medianGrosz instanceof Grosz ? $this->medianGrosz->amount : $this->medianGrosz;
    }

    public function getLow(): Grosz
    {
        return $this->lowGrosz instanceof Grosz ? $this->lowGrosz : Grosz::fromGrosz($this->lowGrosz);
    }

    public function getLowGrosz(): int
    {
        return $this->lowGrosz instanceof Grosz ? $this->lowGrosz->amount : $this->lowGrosz;
    }

    public function getHigh(): Grosz
    {
        return $this->highGrosz instanceof Grosz ? $this->highGrosz : Grosz::fromGrosz($this->highGrosz);
    }

    public function getHighGrosz(): int
    {
        return $this->highGrosz instanceof Grosz ? $this->highGrosz->amount : $this->highGrosz;
    }

    public function getSampleSize(): int
    {
        return $this->sampleSize;
    }

    public function getConfidence(): string
    {
        return $this->confidence;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getMethodology(): string
    {
        return $this->methodology;
    }

    public function updateFromObservation(PriceObservation $observation): void
    {
        $this->medianGrosz = $observation->medianGrosz;
        $this->lowGrosz = $observation->lowGrosz;
        $this->highGrosz = $observation->highGrosz;
        $this->sampleSize = $observation->sampleSize;
        $this->confidence = $observation->confidence;
        $this->summary = $observation->summary !== '' ? $observation->summary : null;
        $this->methodology = $observation->methodology !== '' ? $observation->methodology : PriceObservation::METHODOLOGY_MANUAL;
    }
}
