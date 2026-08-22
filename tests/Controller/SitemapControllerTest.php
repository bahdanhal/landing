<?php

namespace App\Tests\Controller;

use App\Controller\SitemapController;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use PHPUnit\Framework\TestCase;

final class SitemapControllerTest extends TestCase
{
    public function testOnlyProductsWithPublishedObservationsAreIncluded(): void
    {
        $repository = $this->createStub(PriceObservationRepository::class);
        $repository->method('latest')->willReturnCallback(static fn (string $slug): ?PriceObservation => $slug === 'peugeot-206-cc-1-6-petrol'
            ? new PriceObservation($slug, new \DateTimeImmutable('2026-08-21'), 700000, 450000, 1150000, 12, 'medium', '', 'Method')
            : null);

        $xml = (new SitemapController(new ProductCatalog(), $repository))()->getContent();

        self::assertIsString($xml);
        self::assertStringContainsString('<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>', $xml);
        self::assertStringContainsString('/peugeot-206-cc-1-6-petrol</loc>', $xml);
        self::assertStringNotContainsString('/peugeot-206-cc-2-0-petrol</loc>', $xml);
        self::assertSame(14, substr_count($xml, '<url>'));
    }
}
