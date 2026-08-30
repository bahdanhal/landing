<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Lead\Application\CaptureLead;
use App\Portfolio\Presentation\Http\PortfolioController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class PortfolioPagesTest extends TestCase
{
    public function testRendersLandingAboutPage(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('portfolio/home.html.twig', [])
            ->willReturn('<html>About</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = $this->createController();
        $controller->setContainer($container);

        $response = $controller->landing();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>About</html>', $response->getContent());
    }

    public function testRendersResumePage(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('portfolio/resume.html.twig', [])
            ->willReturn('<html>Resume</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = $this->createController();
        $controller->setContainer($container);

        $response = $controller->resume();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>Resume</html>', $response->getContent());
    }

    public function testRendersServicesPage(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('portfolio/services.html.twig', [])
            ->willReturn('<html>Services</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = $this->createController();
        $controller->setContainer($container);

        $response = $controller->services();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>Services</html>', $response->getContent());
    }

    public function testLegacyToolsRedirectsToLanding(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects(self::once())
            ->method('generate')
            ->with('landing', ['_locale' => 'pl'])
            ->willReturn('/pl/');

        $container = new Container();
        $container->set('router', $router);

        $controller = $this->createController();
        $controller->setContainer($container);

        $request = new Request();
        $request->setLocale('pl');

        $response = $controller->legacyTools($request);
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/pl/', $response->headers->get('Location'));
    }

    private function createController(): PortfolioController
    {
        $captureLead = $this->createStub(CaptureLead::class);
        $rateLimiterFactory = new RateLimiterFactory(
            ['id' => 'contact', 'policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 day'],
            new \Symfony\Component\RateLimiter\Storage\InMemoryStorage(),
        );
        $translator = $this->createStub(TranslatorInterface::class);

        return new PortfolioController(
            $captureLead,
            $rateLimiterFactory,
            $translator,
        );
    }
}
