<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Domain\Product;

final readonly class GetMarketStatistics
{
    public function __construct(
        private ProductCatalog $catalog,
        private PriceObservationRepository $observations,
    ) {
    }

    /**
     * @return array{
     *     products: list<array{product: Product, latest: ?PriceObservation, previous: ?PriceObservation, observation_count: int}>,
     *     all_products: list<Product>,
     *     tracked_products: int,
     *     products_with_history: int,
     *     observation_points: int,
     *     products_without_history: list<string>,
     *     stale_products: list<string>,
     *     catalog_coverage_percent: int
     * }
     */
    public function calculate(?\DateTimeImmutable $now = null): array
    {
        $referenceDate = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $thirtyDaysAgo = $referenceDate->modify('-30 days');

        $allProducts = $this->catalog->all();
        $productsData = [];
        $observationCount = 0;
        $productsWithHistory = 0;
        $productsWithoutHistory = [];
        $staleProducts = [];

        foreach ($allProducts as $product) {
            $history = $this->observations->history($product->slug);
            $count = count($history);
            $observationCount += $count;
            $latest = $history[0] ?? null;
            $previous = $history[1] ?? null;

            $productsData[] = [
                'product' => $product,
                'latest' => $latest,
                'previous' => $previous,
                'observation_count' => $count,
            ];

            if ($latest === null) {
                $productsWithoutHistory[] = $product->slug;
                $staleProducts[] = $product->slug;
            } else {
                ++$productsWithHistory;
                if ($latest->observedAt < $thirtyDaysAgo) {
                    $staleProducts[] = $product->slug;
                }
            }
        }

        $trackedCount = count($allProducts);
        $coveragePercent = $trackedCount === 0 ? 0 : (int) round(($productsWithHistory / $trackedCount) * 100);

        return [
            'products' => $productsData,
            'all_products' => $allProducts,
            'tracked_products' => $trackedCount,
            'products_with_history' => $productsWithHistory,
            'observation_points' => $observationCount,
            'products_without_history' => $productsWithoutHistory,
            'stale_products' => $staleProducts,
            'catalog_coverage_percent' => $coveragePercent,
        ];
    }
}
