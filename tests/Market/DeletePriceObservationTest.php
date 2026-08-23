<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\DeletePriceObservation;
use App\Market\Domain\PriceObservationRepository;
use PHPUnit\Framework\TestCase;

final class DeletePriceObservationTest extends TestCase
{
    public function testDeletesObservationSuccessfully(): void
    {
        $repository = $this->createMock(PriceObservationRepository::class);
        $repository->expects(self::once())
            ->method('delete')
            ->with('iphone-13-128gb', '2026-08-20');

        $service = new DeletePriceObservation($repository);
        $service->execute('iphone-13-128gb', '2026-08-20');
    }

    public function testThrowsExceptionForEmptySlug(): void
    {
        $repository = $this->createStub(PriceObservationRepository::class);
        $service = new DeletePriceObservation($repository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product slug cannot be empty');

        $service->execute('', '2026-08-20');
    }

    public function testThrowsExceptionForInvalidDateFormat(): void
    {
        $repository = $this->createStub(PriceObservationRepository::class);
        $service = new DeletePriceObservation($repository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format');

        $service->execute('iphone-13-128gb', '20-08-2026');
    }
}
