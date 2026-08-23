<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;

final readonly class RecordPriceObservation
{
    public function __construct(
        private ProductCatalog $catalog,
        private PriceObservationRepository $observations,
    ) {
    }

    public function execute(
        string $slug,
        \DateTimeImmutable $observedAt,
        int $medianGrosz,
        int $lowGrosz,
        int $highGrosz,
        int $sampleSize = 5,
        string $confidence = 'high',
        string $summary = '',
        string $methodology = PriceObservation::METHODOLOGY_MANUAL,
    ): PriceObservation {
        if ($this->catalog->get($slug) === null) {
            throw new \InvalidArgumentException(sprintf('Unknown product slug: %s', $slug));
        }

        if ($medianGrosz <= 0 || $lowGrosz <= 0 || $highGrosz < $medianGrosz || $medianGrosz < $lowGrosz) {
            throw new \InvalidArgumentException('Inconsistent prices. Ensure low <= median <= high and median > 0.');
        }

        if ($sampleSize < 3) {
            throw new \InvalidArgumentException('Sample size must be at least 3.');
        }

        $confidenceLevel = in_array($confidence, ['low', 'medium', 'high'], true) ? $confidence : 'high';

        $observation = new PriceObservation(
            $slug,
            $observedAt,
            $medianGrosz,
            $lowGrosz,
            $highGrosz,
            $sampleSize,
            $confidenceLevel,
            $summary,
            $methodology !== '' ? $methodology : PriceObservation::METHODOLOGY_MANUAL,
        );

        $this->observations->save($observation);

        return $observation;
    }
}
