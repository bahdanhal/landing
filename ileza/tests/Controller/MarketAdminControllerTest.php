<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Analytics\Application\TrafficAnalytics;
use App\Analytics\Domain\PageViewRepository;
use App\Controller\Admin\MarketAdminController;
use App\Market\Application\DeletePriceObservation;
use App\Market\Application\GetMarketStatistics;
use App\Market\Application\ProductCatalog;
use App\Market\Application\RecordPriceObservation;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Domain\PriceTipRepository;
use App\Market\Domain\ProductRequestStore;
use App\Market\Infrastructure\JsonPriceTipRepository;
use App\Market\Infrastructure\JsonProductRequestStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

final class MarketAdminControllerTest extends TestCase
{
    public function testRendersLoginWhenUnauthenticated(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = new JsonProductRequestStore(sys_get_temp_dir(), 'secret');
        $priceTips = new JsonPriceTipRepository(sys_get_temp_dir(), 'secret');
        $secret = 'test-secret-key';

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('admin/login.html.twig', self::isArray())
            ->willReturn('<html>login</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new MarketAdminController(
            $catalog,
            new GetMarketStatistics($catalog, $observations),
            new RecordPriceObservation($catalog, $observations),
            new DeletePriceObservation($observations),
            $productRequests,
            $priceTips,
            $this->trafficAnalytics(),
            $secret,
        );
        $controller->setContainer($container);

        $request = new Request();
        $response = $controller->index($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>login</html>', $response->getContent());
    }

    public function testRejectsQueryTokenAuthentication(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = new JsonProductRequestStore(sys_get_temp_dir(), 'secret');
        $priceTips = new JsonPriceTipRepository(sys_get_temp_dir(), 'secret');
        $secret = 'test-secret-key';

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('admin/login.html.twig', self::isArray())
            ->willReturn('<html>login</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new MarketAdminController(
            $catalog,
            new GetMarketStatistics($catalog, $observations),
            new RecordPriceObservation($catalog, $observations),
            new DeletePriceObservation($observations),
            $productRequests,
            $priceTips,
            $this->trafficAnalytics(),
            $secret,
        );
        $controller->setContainer($container);

        $response = $controller->index(new Request(query: ['token' => $secret]));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>login</html>', $response->getContent());
    }

    public function testAuthenticatesWithValidLoginPassword(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = new JsonProductRequestStore(sys_get_temp_dir(), 'secret');
        $priceTips = new JsonPriceTipRepository(sys_get_temp_dir(), 'secret');
        $secret = 'test-secret-key';

        $router = $this->createStub(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/market');

        $container = new Container();
        $container->set('router', $router);

        $controller = new MarketAdminController(
            $catalog,
            new GetMarketStatistics($catalog, $observations),
            new RecordPriceObservation($catalog, $observations),
            new DeletePriceObservation($observations),
            $productRequests,
            $priceTips,
            $this->trafficAnalytics(),
            $secret,
        );
        $controller->setContainer($container);

        $request = new Request(request: ['password' => 'test-secret-key']);
        $response = $controller->login($request);

        self::assertSame(302, $response->getStatusCode());
        $cookies = $response->headers->getCookies();
        self::assertNotEmpty($cookies);
        self::assertSame('market_admin_auth', $cookies[0]->getName());
    }

    public function testSavesManualObservation(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createMock(PriceObservationRepository::class);
        $observations->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (PriceObservation $obs): bool {
                return $obs->productSlug === 'iphone-13-128gb'
                    && $obs->medianGrosz === 215000
                    && $obs->lowGrosz === 190000
                    && $obs->highGrosz === 240000
                    && $obs->sampleSize === 6;
            }));

        $productRequests = new JsonProductRequestStore(sys_get_temp_dir(), 'secret');
        $priceTips = new JsonPriceTipRepository(sys_get_temp_dir(), 'secret');
        $secret = 'test-secret-key';

        $router = $this->createStub(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/market');

        $container = new Container();
        $container->set('router', $router);

        $controller = new MarketAdminController(
            $catalog,
            new GetMarketStatistics($catalog, $observations),
            new RecordPriceObservation($catalog, $observations),
            new DeletePriceObservation($observations),
            $productRequests,
            $priceTips,
            $this->trafficAnalytics(),
            $secret,
        );
        $controller->setContainer($container);

        $authCookie = hash_hmac('sha256', 'market_admin_authenticated', $secret);
        $request = new Request(
            request: [
                'product_slug' => 'iphone-13-128gb',
                'observed_at' => '2026-08-22',
                'median_pln' => '2150',
                'low_pln' => '1900',
                'high_pln' => '2400',
                'sample_size' => '6',
                'confidence' => 'high',
            ],
            cookies: ['market_admin_auth' => $authCookie]
        );

        $response = $controller->saveObservation($request);
        self::assertSame(302, $response->getStatusCode());
    }

    public function testAuthenticatedDashboardWithBearerHeader(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $observations->method('history')->willReturn([]);
        $productRequests = $this->createStub(ProductRequestStore::class);
        $productRequests->method('all')->willReturn([]);
        $priceTips = $this->createStub(PriceTipRepository::class);
        $priceTips->method('all')->willReturn([]);
        $secret = 'test-secret-key';

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with(
                'admin/market.html.twig',
                self::callback(static function (array $context): bool {
                    return $context['traffic']['last_7_days']['page_views'] === 0
                        && count($context['traffic']['daily']) === 30
                        && $context['statistics']['catalog_coverage_percent'] === 0
                        && $context['statistics']['stale_products'] === count($context['all_products']);
                }),
            )
            ->willReturn('<html>dashboard</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new MarketAdminController(
            $catalog,
            new GetMarketStatistics($catalog, $observations),
            new RecordPriceObservation($catalog, $observations),
            new DeletePriceObservation($observations),
            $productRequests,
            $priceTips,
            $this->trafficAnalytics(),
            $secret,
        );
        $controller->setContainer($container);

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $secret);
        $response = $controller->index($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>dashboard</html>', $response->getContent());
    }

    public function testAuthenticatedDashboardWithCustomHeader(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $observations->method('history')->willReturn([]);
        $productRequests = $this->createStub(ProductRequestStore::class);
        $productRequests->method('all')->willReturn([]);
        $priceTips = $this->createStub(PriceTipRepository::class);
        $priceTips->method('all')->willReturn([]);
        $secret = 'test-secret-key';

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('admin/market.html.twig', self::isArray())
            ->willReturn('<html>dashboard</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new MarketAdminController(
            $catalog,
            new GetMarketStatistics($catalog, $observations),
            new RecordPriceObservation($catalog, $observations),
            new DeletePriceObservation($observations),
            $productRequests,
            $priceTips,
            $this->trafficAnalytics(),
            $secret,
        );
        $controller->setContainer($container);

        $request = new Request();
        $request->headers->set('X-Admin-Token', $secret);
        $response = $controller->index($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>dashboard</html>', $response->getContent());
    }

    private function trafficAnalytics(): TrafficAnalytics
    {
        $pageViews = $this->createStub(PageViewRepository::class);
        $pageViews->method('since')->willReturn([]);

        return new TrafficAnalytics($pageViews);
    }
}
