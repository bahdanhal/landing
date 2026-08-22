<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Mcp\MarketPriceTools;
use PHPUnit\Framework\TestCase;

final class MarketPriceToolsTest extends TestCase
{
    public function testListProductsReturnsJson(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $tools = new MarketPriceTools($catalog, $repository);

        $json = $tools->listProducts();
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('products', $data);
        self::assertNotEmpty($data['products']);
    }

    public function testGetHistoryReturnsHistory(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $repository->method('history')->willReturn([
            new PriceObservation(
                'iphone-13-128gb',
                new \DateTimeImmutable('2026-08-22'),
                95000,
                83600,
                108300,
                8,
                'high',
                'Verified fair price',
                'Methodology note'
            ),
        ]);

        $tools = new MarketPriceTools($catalog, $repository);
        $json = $tools->getHistory('iphone-13-128gb');
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('iphone-13-128gb', $data['product']['slug']);
        self::assertEquals(950, $data['observations'][0]['median_pln']);
    }

    public function testAdminUpdateObservationUnauthorized(): void
    {
        $_ENV['MARKET_ADMIN_TOKEN'] = 'test-token-123';
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $tools = new MarketPriceTools($catalog, $repository);

        $json = $tools->updateObservation('wrong-token', 'iphone-13-128gb', 950);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('error', $data);
        self::assertStringContainsString('Unauthorized', $data['error']);
    }

    public function testAdminUpdateObservationFailsClosedWhenUnconfigured(): void
    {
        unset($_ENV['MARKET_ADMIN_TOKEN'], $_ENV['APP_SECRET']);
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $tools = new MarketPriceTools($catalog, $repository);

        $json = $tools->updateObservation('bahdan-market-admin-token', 'iphone-13-128gb', 950);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('error', $data);
        self::assertStringContainsString('Unauthorized', $data['error']);
    }

    public function testAdminUpdateObservationSavesSuccessfully(): void
    {
        $_ENV['MARKET_ADMIN_TOKEN'] = 'test-token-123';
        $catalog = new ProductCatalog();
        $repository = $this->createMock(PriceObservationRepository::class);
        $repository->expects(self::once())->method('save');

        $tools = new MarketPriceTools($catalog, $repository);
        $json = $tools->updateObservation(
            'test-token-123',
            'iphone-13-128gb',
            950,
            830,
            1080,
            10,
            'high',
            '2026-08-22',
            'Personal verification'
        );

        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('success', $data['status']);
        self::assertEquals(950, $data['observation']['median_pln']);
    }
}
