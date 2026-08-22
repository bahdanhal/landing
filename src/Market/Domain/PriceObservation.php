<?php

namespace App\Market\Domain;

final readonly class PriceObservation
{
    public function __construct(
        public string $productSlug,
        public \DateTimeImmutable $observedAt,
        public int $medianGrosz,
        public int $lowGrosz,
        public int $highGrosz,
        public int $sampleSize,
        public string $confidence,
        public string $summary,
        public string $methodology,
    ) {
        if ($medianGrosz <= 0 || $lowGrosz <= 0 || $highGrosz < $medianGrosz || $medianGrosz < $lowGrosz) {
            throw new \InvalidArgumentException('Observed prices are inconsistent.');
        }
        if ($sampleSize < 3 || !in_array($confidence, ['low', 'medium', 'high'], true)) {
            throw new \InvalidArgumentException('Observation evidence is insufficient.');
        }
    }

    public function toArray(): array
    {
        return ['product_slug' => $this->productSlug, 'observed_at' => $this->observedAt->format(DATE_ATOM), 'median_grosz' => $this->medianGrosz, 'low_grosz' => $this->lowGrosz, 'high_grosz' => $this->highGrosz, 'sample_size' => $this->sampleSize, 'confidence' => $this->confidence, 'summary' => $this->summary, 'methodology' => $this->methodology];
    }

    public static function fromArray(array $data): self
    {
        return new self((string) $data['product_slug'], new \DateTimeImmutable((string) $data['observed_at']), (int) $data['median_grosz'], (int) $data['low_grosz'], (int) $data['high_grosz'], (int) $data['sample_size'], (string) $data['confidence'], (string) $data['summary'], (string) $data['methodology']);
    }
}
