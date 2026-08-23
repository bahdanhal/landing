<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\ProductCatalog;
use App\Market\Application\RecordPriceObservation;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use PHPUnit\Framework\TestCase;

final class RecordPriceObservationTest extends TestCase
{
    public function testRecordsObservationSuccessfully(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createMock(PriceObservationRepository::class);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (PriceObservation $obs): bool {
                return $obs->productSlug === 'iphone-13-128gb'
                    && $obs->medianGrosz === 210000
                    && $obs->lowGrosz === 190000
                    && $obs->highGrosz === 230000
                    && $obs->sampleSize === 6
                    && $obs->confidence === 'high';
            }));

        $service = new RecordPriceObservation($catalog, $repository);
        $result = $service->execute(
            'iphone-13-128gb',
            new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC')),
            210000,
            190000,
            230000,
            6,
            'high',
            'Summary note'
        );

        self::assertSame('iphone-13-128gb', $result->productSlug);
        self::assertSame(210000, $result->medianGrosz);
    }

    public function testThrowsExceptionForUnknownProduct(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $service = new RecordPriceObservation($catalog, $repository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown product slug');

        $service->execute(
            'non-existent-product',
            new \DateTimeImmutable('2026-08-20'),
            100000,
            90000,
            110000
        );
    }

    public function testThrowsExceptionForInconsistentPrices(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $service = new RecordPriceObservation($catalog, $repository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Inconsistent prices');

        $service->execute(
            'iphone-13-128gb',
            new \DateTimeImmutable('2026-08-20'),
            200000,
            220000, // low > median
            250000
        );
    }

    public function testThrowsExceptionForSmallSampleSize(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $service = new RecordPriceObservation($catalog, $repository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sample size must be at least 3');

        $service->execute(
            'iphone-13-128gb',
            new \DateTimeImmutable('2026-08-20'),
            200000,
            180000,
            220000,
            2 // sample size < 3
        );
    }
}
