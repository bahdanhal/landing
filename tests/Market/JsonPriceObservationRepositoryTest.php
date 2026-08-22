<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Domain\PriceObservation;
use App\Market\Infrastructure\JsonPriceObservationRepository;
use PHPUnit\Framework\TestCase;

final class JsonPriceObservationRepositoryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/market-repository-' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testItStoresNewestFirstAndReplacesTheSameDay(): void
    {
        $repository = new JsonPriceObservationRepository($this->directory);
        $repository->save($this->observation('2026-08-14T10:00:00+02:00', 160000));
        $repository->save($this->observation('2026-08-21T10:00:00+02:00', 150000));
        $repository->save($this->observation('2026-08-21T12:00:00+02:00', 149000));

        $history = $repository->history('iphone-14-128gb');
        self::assertCount(2, $history);
        self::assertSame(149000, $history[0]->medianGrosz);
        self::assertSame(160000, $history[1]->medianGrosz);
        self::assertSame(149000, $repository->latest('iphone-14-128gb')?->medianGrosz);
    }

    private function observation(string $date, int $median): PriceObservation
    {
        return new PriceObservation(
            'iphone-14-128gb',
            new \DateTimeImmutable($date),
            $median,
            $median - 10000,
            $median + 10000,
            12,
            'medium',
            'Summary',
            'Method'
        );
    }
}
