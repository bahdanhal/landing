<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Kernel;
use App\Mcp\GeoTools;
use App\Service\GeoAnalyzer;
use App\Service\HttpFetcher;
use App\Service\PageAnalyzer;
use App\Service\RobotsPolicy;
use App\Service\UrlGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

final class GeoAnalyzerLandingSelfAuditTest extends TestCase
{
    private Kernel $kernel;
    private ContainerInterface $container;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->kernel = new Kernel('test', true);
        $this->kernel->boot();
        $this->container = $this->kernel->getContainer()->get('test.service_container');
        /** @var Environment $twig */
        $twig = $this->container->get('twig');
        $this->twig = $twig;
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testLandingPageEnScores100OnSelfGeoAudit(): void
    {
        $requestStack = $this->container->get('request_stack');
        $request = Request::create('https://bahdan-hal.ovh/');
        $request->setLocale('en');
        $requestStack->push($request);

        $html = $this->twig->render('portfolio/home.html.twig');
        $report = $this->runGeoAudit('https://bahdan-hal.ovh/', $html);

        $imperfectChecks = array_values(array_filter($report['checks'], static fn (array $c): bool => $c['earned'] !== $c['maximum']));
        self::assertSame([], $imperfectChecks, 'All checks must earn maximum points');
        self::assertSame(100, $report['score'], 'Score must be 100/100');
        self::assertSame(13, $report['counts']['pass'], 'All 13 checks must pass');
        self::assertSame(0, $report['counts']['warning'], 'There must be 0 warnings');
        self::assertSame(0, $report['counts']['fail'], 'There must be 0 failures');

        self::assertTrue($report['crawler_controls']['llms_txt_present']);
        self::assertSame('allowed', $report['crawler_controls']['policies']['GPTBot']);
        self::assertSame('allowed', $report['crawler_controls']['policies']['ClaudeBot']);

        $requestStack->pop();
    }

    public function testLandingPagePlScores100OnSelfGeoAudit(): void
    {
        $requestStack = $this->container->get('request_stack');
        $request = Request::create('https://bahdan-hal.ovh/pl/');
        $request->setLocale('pl');
        $requestStack->push($request);

        $html = $this->twig->render('portfolio/home.html.twig');
        $report = $this->runGeoAudit('https://bahdan-hal.ovh/pl/', $html);

        $imperfectChecks = array_values(array_filter($report['checks'], static fn (array $c): bool => $c['earned'] !== $c['maximum']));
        self::assertSame([], $imperfectChecks, 'All Polish checks must earn maximum points');
        self::assertSame(100, $report['score'], 'Score must be 100/100 for Polish landing page');
        self::assertSame(13, $report['counts']['pass']);
        self::assertSame(0, $report['counts']['warning']);
        self::assertSame(0, $report['counts']['fail']);

        $requestStack->pop();
    }

    public function testMcpToolReturnsCompletedGeoAnalysisForLanding(): void
    {
        $requestStack = $this->container->get('request_stack');
        $request = Request::create('https://bahdan-hal.ovh/');
        $request->setLocale('en');
        $requestStack->push($request);

        $html = $this->twig->render('portfolio/home.html.twig');
        $analyzer = $this->createAnalyzerForHtml('https://bahdan-hal.ovh/', $html);
        $geoTools = new GeoTools($analyzer);

        $jsonResult = $geoTools->analyzeGeo('https://bahdan-hal.ovh/');
        $data = json_decode($jsonResult, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('completed', $data['status']);
        self::assertSame(100, $data['score']);
        self::assertSame('https://bahdan-hal.ovh/', $data['target']);
        self::assertCount(13, $data['checks']);

        $requestStack->pop();
    }

    private function runGeoAudit(string $url, string $html): array
    {
        $analyzer = $this->createAnalyzerForHtml($url, $html);
        return $analyzer->analyze($url);
    }

    private function createAnalyzerForHtml(string $url, string $html): GeoAnalyzer
    {
        $robotsTxt = (string) file_get_contents(dirname(__DIR__, 2) . '/public/robots.txt');
        $llmsTxt = (string) file_get_contents(dirname(__DIR__, 2) . '/public/llms.txt');

        $urlGuard = new UrlGuard();
        $fetcher = new class ($url, $html, $robotsTxt, $llmsTxt) extends HttpFetcher {
            public function __construct(
                private readonly string $targetUrl,
                private readonly string $html,
                private readonly string $robotsTxt,
                private readonly string $llmsTxt,
            ) {
            }

            public function fetch(string $url, int $maxRedirects = 8): array
            {
                if ($url === $this->targetUrl) {
                    return [
                        'requested_url' => $url,
                        'final_url' => $url,
                        'status' => 200,
                        'headers' => ['content-type' => ['text/html; charset=UTF-8']],
                        'body' => $this->html,
                        'content_type' => 'text/html; charset=UTF-8',
                        'duration_ms' => 10,
                        'redirects' => [],
                        'error' => null,
                    ];
                }

                if (str_ends_with($url, '/robots.txt')) {
                    return [
                        'requested_url' => $url,
                        'final_url' => $url,
                        'status' => 200,
                        'headers' => ['content-type' => ['text/plain']],
                        'body' => $this->robotsTxt,
                        'content_type' => 'text/plain',
                        'duration_ms' => 5,
                        'redirects' => [],
                        'error' => null,
                    ];
                }

                if (str_ends_with($url, '/llms.txt')) {
                    return [
                        'requested_url' => $url,
                        'final_url' => $url,
                        'status' => 200,
                        'headers' => ['content-type' => ['text/plain']],
                        'body' => $this->llmsTxt,
                        'content_type' => 'text/plain',
                        'duration_ms' => 5,
                        'redirects' => [],
                        'error' => null,
                    ];
                }

                return [
                    'requested_url' => $url,
                    'final_url' => $url,
                    'status' => 404,
                    'headers' => [],
                    'body' => '',
                    'content_type' => '',
                    'duration_ms' => 5,
                    'redirects' => [],
                    'error' => 'Not Found',
                ];
            }
        };

        $pageAnalyzer = new PageAnalyzer($fetcher);
        $robotsPolicy = new RobotsPolicy();
        $cache = new ArrayAdapter();

        return new GeoAnalyzer($urlGuard, $fetcher, $pageAnalyzer, $robotsPolicy, $cache, 3600);
    }
}
