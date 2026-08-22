<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\MarketAdminController;
use App\Market\Application\MarketResearcher;
use App\Market\Application\ObserveMarket;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
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
        $researcher = $this->createStub(MarketResearcher::class);
        $observeMarket = new ObserveMarket($catalog, $researcher, $observations);
        $secret = 'test-secret-key';

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('admin/login.html.twig', self::isArray())
            ->willReturn('<html>login</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new MarketAdminController($catalog, $observations, $productRequests, $observeMarket, $secret);
        $controller->setContainer($container);

        $request = new Request();
        $response = $controller->index($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>login</html>', $response->getContent());
    }

    public function testAuthenticatesWithValidToken(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = new JsonProductRequestStore(sys_get_temp_dir(), 'secret');
        $researcher = $this->createStub(MarketResearcher::class);
        $observeMarket = new ObserveMarket($catalog, $researcher, $observations);
        $secret = 'test-secret-key';

        $router = $this->createStub(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/market');

        $container = new Container();
        $container->set('router', $router);

        $controller = new MarketAdminController($catalog, $observations, $productRequests, $observeMarket, $secret);
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
        $researcher = $this->createStub(MarketResearcher::class);
        $observeMarket = new ObserveMarket($catalog, $researcher, $observations);
        $secret = 'test-secret-key';

        $router = $this->createStub(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/market');

        $container = new Container();
        $container->set('router', $router);

        $controller = new MarketAdminController($catalog, $observations, $productRequests, $observeMarket, $secret);
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
}
