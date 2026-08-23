<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Market\Application\GetProductPriceHistory;
use App\Market\Application\ProductCatalog;
use App\Market\Application\RecordPriceObservation;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Mcp\AdminAccess;
use App\Mcp\MarketPriceTools;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class MarketPriceToolsTest extends TestCase
{
    public function testListProductsReturnsJson(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $repository),
            new RecordPriceObservation($catalog, $repository),
        );

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

        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $repository),
            new RecordPriceObservation($catalog, $repository),
        );
        $json = $tools->getHistory('iphone-13-128gb');
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('iphone-13-128gb', $data['product']['slug']);
        self::assertEquals(950, $data['observations'][0]['median_pln']);
    }

    public function testAdminUpdateObservationUnauthorized(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer wrong-token');
        $requestStack->push($request);
        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $repository),
            new RecordPriceObservation($catalog, $repository),
            new AdminAccess($requestStack, 'test-token-123'),
        );

        $json = $tools->updateObservation('iphone-13-128gb', 950);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('error', $data);
        self::assertStringContainsString('Unauthorized', $data['error']);
    }

    public function testAdminUpdateObservationFailsClosedWhenUnconfigured(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $repository),
            new RecordPriceObservation($catalog, $repository),
            new AdminAccess(new RequestStack(), ''),
        );

        $json = $tools->updateObservation('iphone-13-128gb', 950);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('error', $data);
        self::assertStringContainsString('Unauthorized', $data['error']);
    }

    public function testAdminUpdateObservationFailsWithoutHeader(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceObservationRepository::class);
        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $repository),
            new RecordPriceObservation($catalog, $repository),
            new AdminAccess(new RequestStack(), 'test-token-123'),
        );

        $json = $tools->updateObservation('iphone-13-128gb', 950);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('error', $data);
        self::assertStringContainsString('Unauthorized', $data['error']);
    }

    public function testAdminUpdateObservationSavesSuccessfullyWithBearerToken(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createMock(PriceObservationRepository::class);
        $repository->expects(self::once())->method('save');

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer test-token-123');
        $requestStack->push($request);

        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $repository),
            new RecordPriceObservation($catalog, $repository),
            new AdminAccess($requestStack, 'test-token-123'),
        );
        $json = $tools->updateObservation(
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

    public function testAdminUpdateObservationSavesSuccessfullyWithAuthorizationHeader(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createMock(PriceObservationRepository::class);
        $repository->expects(self::once())->method('save');

        $requestStack = new RequestStack();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer test-token-123');
        $requestStack->push($request);

        $tools = new MarketPriceTools(
            $catalog,
            new GetProductPriceHistory($catalog, $repository),
            new RecordPriceObservation($catalog, $repository),
            new AdminAccess($requestStack, 'test-token-123'),
        );
        $json = $tools->updateObservation(
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
