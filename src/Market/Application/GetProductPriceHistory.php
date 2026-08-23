<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Domain\Product;
use App\Market\Domain\ProductFamily;

final readonly class GetProductPriceHistory
{
    public function __construct(
        private ProductCatalog $catalog,
        private PriceObservationRepository $observations,
    ) {
    }

    /**
     * @return list<PriceObservation>
     */
    public function forProduct(string $slug): array
    {
        return $this->observations->history($slug);
    }

    public function latestForProduct(string $slug): ?PriceObservation
    {
        return $this->observations->latest($slug);
    }

    /**
     * @return array{
     *     product: Product,
     *     family: ?ProductFamily,
     *     history: list<PriceObservation>,
     *     latest: ?PriceObservation,
     *     one_month_ago: ?PriceObservation
     * }|null
     */
    public function detailedHistory(string $slug): ?array
    {
        $product = $this->catalog->get($slug);
        if ($product === null) {
            return null;
        }

        $history = $this->observations->history($slug);
        $family = $this->catalog->familyFor($slug);
        $latest = $history[0] ?? null;

        $oneMonthAgo = null;
        if ($latest !== null) {
            $targetTimestamp = $latest->observedAt->getTimestamp() - (30 * 86400);
            $closestDiff = null;
            foreach (array_slice($history, 1) as $item) {
                $diff = abs($item->observedAt->getTimestamp() - $targetTimestamp);
                if ($diff <= 15 * 86400) {
                    if ($closestDiff === null || $diff < $closestDiff) {
                        $closestDiff = $diff;
                        $oneMonthAgo = $item;
                    }
                }
            }
        }

        return [
            'product' => $product,
            'family' => $family,
            'history' => $history,
            'latest' => $latest,
            'one_month_ago' => $oneMonthAgo,
        ];
    }
}
