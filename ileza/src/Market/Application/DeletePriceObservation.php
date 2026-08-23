<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\PriceObservationRepository;

final readonly class DeletePriceObservation
{
    public function __construct(
        private PriceObservationRepository $observations,
    ) {
    }

    public function execute(string $slug, string $date): void
    {
        $cleanSlug = trim($slug);
        $cleanDate = trim($date);

        if ($cleanSlug === '') {
            throw new \InvalidArgumentException('Product slug cannot be empty.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cleanDate)) {
            throw new \InvalidArgumentException('Invalid date format. Expected YYYY-MM-DD.');
        }

        $this->observations->delete($cleanSlug, $cleanDate);
    }
}
