<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\MarketController;
use App\Market\Application\GetProductPriceHistory;
use App\Market\Application\ProductCatalog;
use App\Market\Application\RecordProductRequest;
use App\Market\Application\SubmitCommunityPriceTip;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Domain\PriceTip;
use App\Market\Domain\PriceTipRepository;
use App\Market\Domain\ProductRequestStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class MarketControllerTest extends TestCase
{
    public function testHomeRendersWithFamilies(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = $this->createStub(ProductRequestStore::class);
        $priceTips = $this->createStub(PriceTipRepository::class);
        $translator = $this->createStub(TranslatorInterface::class);

        $requestLimiter = $this->createRateLimiterFactory();
        $tipLimiter = $this->createRateLimiterFactory();

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('market/home.html.twig', self::isArray())
            ->willReturn('<html>home</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $translator
        );
        $controller->setContainer($container);

        $response = $controller->home();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>home</html>', $response->getContent());
    }

    public function testLegacyHomeRedirectsPermanently(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = $this->createStub(ProductRequestStore::class);
        $priceTips = $this->createStub(PriceTipRepository::class);
        $translator = $this->createStub(TranslatorInterface::class);

        $requestLimiter = $this->createRateLimiterFactory();
        $tipLimiter = $this->createRateLimiterFactory();

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $translator
        );

        $router = $this->createStub(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(function ($name, $params) {
            return ($params['_locale'] === 'pl' ? '/pl/' : '/');
        });

        $container = new Container();
        $container->set('router', $router);
        $controller->setContainer($container);

        $request = new Request();
        $request->setLocale('pl');

        $response = $controller->legacyHome($request);
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/pl/', $response->headers->get('Location'));
    }

    public function testProductRendersDetailedHistory(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createMock(PriceObservationRepository::class);
        $observations->method('history')->with('iphone-13-128gb')->willReturn([
            new PriceObservation(
                'iphone-13-128gb',
                new \DateTimeImmutable('2026-08-20'),
                210000,
                190000,
                230000,
                5,
                'high',
                '',
                PriceObservation::METHODOLOGY_MANUAL
            ),
        ]);

        $productRequests = $this->createStub(ProductRequestStore::class);
        $priceTips = $this->createStub(PriceTipRepository::class);
        $translator = $this->createStub(TranslatorInterface::class);
        $requestLimiter = $this->createRateLimiterFactory();
        $tipLimiter = $this->createRateLimiterFactory();

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('market/product.html.twig', self::callback(static function (array $context): bool {
                return $context['product']->slug === 'iphone-13-128gb'
                    && $context['latest'] !== null;
            }))
            ->willReturn('<html>product</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $translator
        );
        $controller->setContainer($container);

        $response = $controller->product('iphone-13-128gb');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testRequestProductSavesSuccessfully(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = $this->createMock(ProductRequestStore::class);
        $productRequests->expects(self::once())
            ->method('save')
            ->with('iPhone 15 Pro', 'test@example.com', '127.0.0.1');

        $priceTips = $this->createStub(PriceTipRepository::class);
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Saved');

        $requestLimiter = $this->createRateLimiterFactory();
        $tipLimiter = $this->createRateLimiterFactory();

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $translator
        );
        $container = new Container();
        $controller->setContainer($container);

        $request = new Request(
            request: ['product' => 'iPhone 15 Pro', 'email' => 'test@example.com'],
            server: ['REMOTE_ADDR' => '127.0.0.1']
        );

        $response = $controller->requestProduct($request);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testSubmitPriceTipSavesSuccessfully(): void
    {
        $catalog = new ProductCatalog();
        $observations = $this->createStub(PriceObservationRepository::class);
        $productRequests = $this->createStub(ProductRequestStore::class);
        $priceTips = $this->createMock(PriceTipRepository::class);
        $priceTips->expects(self::once())
            ->method('submit')
            ->with('iphone-13-128gb', 'https://allegro.pl/oferta/test-123', 'tip@example.com', '127.0.0.1')
            ->willReturn(new PriceTip(
                'iphone-13-128gb',
                'https://allegro.pl/oferta/test-123',
                'tip@example.com',
                'hash',
                new \DateTimeImmutable(),
                new \DateTimeImmutable('+90 days')
            ));

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Saved');

        $requestLimiter = $this->createRateLimiterFactory();
        $tipLimiter = $this->createRateLimiterFactory();

        $controller = new MarketController(
            $catalog,
            new GetProductPriceHistory($catalog, $observations),
            new RecordProductRequest($productRequests),
            $requestLimiter,
            new SubmitCommunityPriceTip($catalog, $priceTips),
            $tipLimiter,
            $translator
        );
        $container = new Container();
        $controller->setContainer($container);

        $request = new Request(
            request: ['listing_url' => 'https://allegro.pl/oferta/test-123', 'email' => 'tip@example.com'],
            server: ['REMOTE_ADDR' => '127.0.0.1']
        );

        $response = $controller->submitPriceTip('iphone-13-128gb', $request);
        self::assertSame(200, $response->getStatusCode());
    }

    private function createRateLimiterFactory(): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => 'test-limiter',
            'policy' => 'fixed_window',
            'limit' => 10,
            'interval' => '1 minute',
        ], new InMemoryStorage());
    }
}
