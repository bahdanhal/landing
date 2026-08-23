<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\SitemapController;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use PHPUnit\Framework\TestCase;

final class SitemapControllerTest extends TestCase
{
    public function testGeneratesValidXmlSitemapWithHeaders(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $observations->method('latest')->willReturn(null);

        $controller = new SitemapController($catalog, $observations);
        $response = $controller();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/xml; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame(300, $response->getMaxAge());
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));

        $content = (string) $response->getContent();
        self::assertStringContainsString('<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>', $content);
        self::assertStringContainsString('<loc>https://ileza.pl/</loc>', $content);
        self::assertStringContainsString('<loc>https://ileza.pl/pl/</loc>', $content);
        self::assertStringContainsString('<loc>https://ileza.pl/salary-calculator</loc>', $content);
        self::assertStringContainsString('<loc>https://ileza.pl/kalkulator-wynagrodzen</loc>', $content);

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($content));
    }

    public function testOnlyIncludesProductsWithObservations(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $observations->method('latest')->willReturnCallback(static function (string $slug): ?PriceObservation {
            if ($slug === 'peugeot-206-cc-1-6-petrol') {
                return new PriceObservation(
                    'peugeot-206-cc-1-6-petrol',
                    new \DateTimeImmutable('2026-08-21T12:00:00+02:00'),
                    1200000,
                    1000000,
                    1400000,
                    8,
                    'medium',
                    '',
                    PriceObservation::METHODOLOGY_MANUAL,
                );
            }

            return null;
        });

        $controller = new SitemapController($catalog, $observations);
        $response = $controller();

        $content = (string) $response->getContent();
        $expectedUrl = 'https://ileza.pl/prices/peugeot-206-cc-1-6-petrol';
        $expectedPlUrl = 'https://ileza.pl/ceny/peugeot-206-cc-1-6-petrol';
        self::assertStringContainsString($expectedUrl, $content);
        self::assertStringContainsString($expectedPlUrl, $content);
        self::assertStringContainsString('<lastmod>2026-08-21</lastmod>', $content);
        self::assertStringNotContainsString('peugeot-206-cc-2-0-petrol', $content);
    }
}
