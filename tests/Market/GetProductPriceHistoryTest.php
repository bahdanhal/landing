<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\GetProductPriceHistory;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use PHPUnit\Framework\TestCase;

final class GetProductPriceHistoryTest extends TestCase
{
    public function testReturnsDetailedHistoryWithOneMonthComparison(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createMock(PriceObservationRepository::class);

        $now = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
        $oneMonthAgo = $now->modify('-30 days');

        $latestObs = new PriceObservation(
            'iphone-13-128gb',
            $now,
            200000,
            180000,
            220000,
            6,
            'high',
            '',
            PriceObservation::METHODOLOGY_MANUAL
        );

        $pastObs = new PriceObservation(
            'iphone-13-128gb',
            $oneMonthAgo,
            210000,
            190000,
            230000,
            5,
            'high',
            '',
            PriceObservation::METHODOLOGY_MANUAL
        );

        $repository->method('history')->with('iphone-13-128gb')->willReturn([$latestObs, $pastObs]);

        $service = new GetProductPriceHistory($catalog, $repository);
        $result = $service->detailedHistory('iphone-13-128gb');

        self::assertNotNull($result);
        self::assertSame('iphone-13-128gb', $result['product']->slug);
        self::assertSame($latestObs, $result['latest']);
        self::assertSame($pastObs, $result['one_month_ago']);
    }

    public function testReturnsNullForUnknownProduct(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);

        $service = new GetProductPriceHistory($catalog, $repository);
        $result = $service->detailedHistory('unknown-device');

        self::assertNull($result);
    }
}
