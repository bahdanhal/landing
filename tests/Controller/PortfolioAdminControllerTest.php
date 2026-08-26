<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Analytics\Application\TrafficAnalytics;
use App\Analytics\Domain\PageViewRepository;
use App\Controller\Admin\PortfolioAdminController;
use App\Lead\Domain\Lead;
use App\Lead\Domain\LeadRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

final class PortfolioAdminControllerTest extends TestCase
{
    public function testRendersLoginWhenUnauthenticated(): void
    {
        $leads = $this->createStub(LeadRepository::class);
        $secret = 'test-secret-key';

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('admin/login.html.twig', self::isArray())
            ->willReturn('<html>login</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new PortfolioAdminController(
            $leads,
            $this->trafficAnalytics(),
            $secret,
        );
        $controller->setContainer($container);

        $request = new Request();
        $response = $controller->index($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>login</html>', $response->getContent());
    }

    public function testAuthenticatesWithValidLoginPassword(): void
    {
        $leads = $this->createStub(LeadRepository::class);
        $secret = 'test-secret-key';

        $router = $this->createStub(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin');

        $container = new Container();
        $container->set('router', $router);

        $controller = new PortfolioAdminController(
            $leads,
            $this->trafficAnalytics(),
            $secret,
        );
        $controller->setContainer($container);

        $validToken = hash_hmac('sha256', 'csrf:portfolio_admin_login', $secret);
        $request = new Request(request: ['password' => 'test-secret-key', '_token' => $validToken]);
        $response = $controller->login($request);

        self::assertSame(302, $response->getStatusCode());
        $cookies = $response->headers->getCookies();
        self::assertNotEmpty($cookies);
        self::assertSame('portfolio_admin_auth', $cookies[0]->getName());
    }

    public function testRejectsEmptyPasswordLogin(): void
    {
        $leads = $this->createStub(LeadRepository::class);
        $secret = 'test-secret-key';

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('admin/login.html.twig', self::callback(static function (array $context): bool {
                return isset($context['error']) && str_contains($context['error'], 'Invalid admin token');
            }))
            ->willReturn('<html>login error</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new PortfolioAdminController(
            $leads,
            $this->trafficAnalytics(),
            $secret,
        );
        $controller->setContainer($container);

        $validToken = hash_hmac('sha256', 'csrf:portfolio_admin_login', $secret);
        $request = new Request(request: ['password' => '', '_token' => $validToken]);
        $response = $controller->login($request);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRejectsLoginWithInvalidCsrfToken(): void
    {
        $leads = $this->createStub(LeadRepository::class);
        $secret = 'test-secret-key';

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('admin/login.html.twig', self::callback(static function (array $context): bool {
                return isset($context['error']) && str_contains($context['error'], 'CSRF');
            }))
            ->willReturn('<html>login error</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new PortfolioAdminController(
            $leads,
            $this->trafficAnalytics(),
            $secret,
        );
        $controller->setContainer($container);

        $request = new Request(request: ['password' => 'test-secret-key', '_token' => 'invalid-token']);
        $response = $controller->login($request);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRendersDashboardWhenAuthenticated(): void
    {
        $leads = $this->createStub(LeadRepository::class);
        $lead = Lead::create('client@example.com', '+48123456789', 'Need backend architecture help', 'hash123', 'website');
        $leads->method('all')->willReturn([$lead]);
        $secret = 'test-secret-key';

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with(
                'admin/dashboard.html.twig',
                self::callback(static function (array $context): bool {
                    return $context['total_leads'] === 1
                        && count($context['leads']) === 1
                        && $context['traffic']['last_7_days']['page_views'] === 0;
                }),
            )
            ->willReturn('<html>dashboard</html>');

        $container = new Container();
        $container->set('twig', $twig);

        $controller = new PortfolioAdminController(
            $leads,
            $this->trafficAnalytics(),
            $secret,
        );
        $controller->setContainer($container);

        $authCookie = hash_hmac('sha256', 'portfolio_admin_authenticated', $secret);
        $request = new Request(cookies: ['portfolio_admin_auth' => $authCookie]);
        $response = $controller->index($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>dashboard</html>', $response->getContent());
    }

    public function testLogoutClearsCookie(): void
    {
        $leads = $this->createStub(LeadRepository::class);
        $secret = 'test-secret-key';

        $router = $this->createStub(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin');

        $container = new Container();
        $container->set('router', $router);

        $controller = new PortfolioAdminController(
            $leads,
            $this->trafficAnalytics(),
            $secret,
        );
        $controller->setContainer($container);

        $response = $controller->logout();
        self::assertSame(302, $response->getStatusCode());
    }

    private function trafficAnalytics(): TrafficAnalytics
    {
        $pageViews = $this->createStub(PageViewRepository::class);
        $pageViews->method('since')->willReturn([]);
        $pageViews->method('summary')->willReturn([
            'privacy' => 'Cookie-free aggregates.',
            'last_7_days' => ['page_views' => 0, 'unique_visitors' => 0, 'sources' => [], 'referring_domains' => [], 'top_paths' => []],
            'last_30_days' => ['page_views' => 0, 'unique_visitors' => 0, 'sources' => [], 'referring_domains' => [], 'top_paths' => []],
            'daily' => [],
        ]);

        return new TrafficAnalytics($pageViews);
    }
}
