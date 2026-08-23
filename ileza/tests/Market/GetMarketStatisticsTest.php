<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\GetMarketStatistics;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use PHPUnit\Framework\TestCase;

final class GetMarketStatisticsTest extends TestCase
{
    public function testCalculatesMarketCoverageAndStaleness(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);

        $now = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
        $recentDate = $now->modify('-5 days');

        $repository->method('history')->willReturnCallback(static function (string $slug) use ($recentDate): array {
            if ($slug === 'iphone-13-128gb') {
                return [
                    new PriceObservation(
                        'iphone-13-128gb',
                        $recentDate,
                        210000,
                        190000,
                        230000,
                        5,
                        'high',
                        '',
                        PriceObservation::METHODOLOGY_MANUAL
                    ),
                ];
            }
            return [];
        });

        $service = new GetMarketStatistics($catalog, $repository);
        $stats = $service->calculate($now);

        self::assertGreaterThan(0, $stats['tracked_products']);
        self::assertSame(1, $stats['products_with_history']);
        self::assertSame(1, $stats['observation_points']);
        self::assertContains('iphone-13-256gb', $stats['products_without_history']);
        self::assertNotContains('iphone-13-128gb', $stats['stale_products']);
    }
}
