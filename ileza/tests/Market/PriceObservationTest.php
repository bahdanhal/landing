<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Domain\PriceObservation;
use PHPUnit\Framework\TestCase;

final class PriceObservationTest extends TestCase
{
    public function testItRoundTripsAValidObservation(): void
    {
        $observation = $this->observation();

        self::assertEquals($observation, PriceObservation::fromArray($observation->toArray()));
    }

    public function testItRejectsAnInconsistentPriceRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PriceObservation(
            'iphone-14-128gb',
            new \DateTimeImmutable('2026-08-21'),
            150000,
            160000,
            140000,
            8,
            'medium',
            'Summary',
            'Method'
        );
    }

    public function testLegacyResearchProseIsDiscardedOnRead(): void
    {
        $data = $this->observation()->toArray();
        $data['summary'] = 'A legacy sentence naming an exact marketplace.';
        $data['methodology'] = 'Legacy source details.';

        $observation = PriceObservation::fromArray($data);

        self::assertSame('', $observation->summary);
        self::assertStringNotContainsString('Legacy', $observation->methodology);
    }

    public function testLegacyMethodologyIsNormalizedToManualReview(): void
    {
        $data = $this->observation()->toArray();
        $data['methodology'] = 'AI-assisted legacy methodology';

        self::assertSame(PriceObservation::METHODOLOGY_MANUAL, PriceObservation::fromArray($data)->methodology);
    }

    private function observation(): PriceObservation
    {
        return new PriceObservation(
            'iphone-14-128gb',
            new \DateTimeImmutable('2026-08-21T12:00:00+02:00'),
            150000,
            130000,
            170000,
            8,
            'medium',
            '',
            PriceObservation::METHODOLOGY_MANUAL
        );
    }
}
