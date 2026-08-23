<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Domain\PriceObservation;
use App\Market\Infrastructure\DoctrinePriceObservationRepository;
use App\Tests\DoctrineTestCase;

final class DoctrinePriceObservationRepositoryTest extends DoctrineTestCase
{
    public function testSavesRetrievesAndDeletesObservations(): void
    {
        $repository = new DoctrinePriceObservationRepository($this->entityManager);

        self::assertEmpty($repository->history('iphone-13-128gb'));
        self::assertNull($repository->latest('iphone-13-128gb'));

        $obs1 = new PriceObservation(
            'iphone-13-128gb',
            new \DateTimeImmutable('2026-08-01 12:00:00', new \DateTimeZone('UTC')),
            210000,
            190000,
            230000,
            5,
            'high',
            '',
            PriceObservation::METHODOLOGY_MANUAL
        );

        $obs2 = new PriceObservation(
            'iphone-13-128gb',
            new \DateTimeImmutable('2026-08-15 12:00:00', new \DateTimeZone('UTC')),
            205000,
            185000,
            225000,
            6,
            'high',
            '',
            PriceObservation::METHODOLOGY_MANUAL
        );

        $repository->save($obs1);
        $repository->save($obs2);

        $history = $repository->history('iphone-13-128gb');
        self::assertCount(2, $history);
        self::assertSame(205000, $history[0]->medianGrosz);
        self::assertSame(210000, $history[1]->medianGrosz);

        $latest = $repository->latest('iphone-13-128gb');
        self::assertNotNull($latest);
        self::assertSame(205000, $latest->medianGrosz);

        $repository->delete('iphone-13-128gb', '2026-08-15');
        $afterDelete = $repository->history('iphone-13-128gb');
        self::assertCount(1, $afterDelete);
        self::assertSame(210000, $afterDelete[0]->medianGrosz);
    }

    public function testUpdatesExistingObservationInPlace(): void
    {
        $repository = new DoctrinePriceObservationRepository($this->entityManager);
        $date = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));

        $initial = new PriceObservation(
            'iphone-13-128gb',
            $date,
            210000,
            190000,
            230000,
            5,
            'medium',
            'Initial estimate',
            PriceObservation::METHODOLOGY_MANUAL
        );
        $repository->save($initial);

        $updated = new PriceObservation(
            'iphone-13-128gb',
            $date,
            215000,
            195000,
            235000,
            8,
            'high',
            'Updated estimate',
            PriceObservation::METHODOLOGY_MANUAL
        );
        $repository->save($updated);

        $history = $repository->history('iphone-13-128gb');
        self::assertCount(1, $history);
        self::assertSame(215000, $history[0]->medianGrosz);
        self::assertSame(195000, $history[0]->lowGrosz);
        self::assertSame(235000, $history[0]->highGrosz);
        self::assertSame(8, $history[0]->sampleSize);
        self::assertSame('high', $history[0]->confidence);
        self::assertSame('Updated estimate', $history[0]->summary);
    }
}
