<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Crawl\Application\PageAnalyzer;
use App\Crawl\Domain\RobotsPolicy;
use App\Geo\Application\GeoAnalyzer;
use App\Kernel;
use App\Mcp\GeoTools;
use App\Shared\Infrastructure\Http\SafeHttpFetcher;
use App\Shared\Infrastructure\Http\UrlGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
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
        /** @var ContainerInterface $container */
        $container = $this->kernel->getContainer()->get('test.service_container');
        $this->container = $container;
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
        /** @var RequestStack $requestStack */
        $requestStack = $this->container->get('request_stack');
        $request = Request::create('https://stackhal.com/');
        $request->setLocale('en');
        $requestStack->push($request);

        $html = $this->twig->render('toolbox/home.html.twig');
        $report = $this->runGeoAudit('https://stackhal.com/', $html);

        self::assertGreaterThanOrEqual(80, $report['score']);
        self::assertTrue($report['crawler_controls']['llms_txt_present']);

        $requestStack->pop();
    }

    public function testLandingPagePlScores100OnSelfGeoAudit(): void
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->container->get('request_stack');
        $request = Request::create('https://stackhal.com/pl/');
        $request->setLocale('pl');
        $requestStack->push($request);

        $html = $this->twig->render('toolbox/home.html.twig');
        $report = $this->runGeoAudit('https://stackhal.com/pl/', $html);

        self::assertGreaterThanOrEqual(70, $report['score']);

        $requestStack->pop();
    }

    public function testMcpToolReturnsCompletedGeoAnalysisForLanding(): void
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->container->get('request_stack');
        $request = Request::create('https://stackhal.com/');
        $request->setLocale('en');
        $requestStack->push($request);

        $html = $this->twig->render('toolbox/home.html.twig');
        $analyzer = $this->createAnalyzerForHtml('https://stackhal.com/', $html);
        $geoTools = new GeoTools($analyzer);

        $jsonResult = $geoTools->analyzeGeo('https://stackhal.com/');
        $data = json_decode($jsonResult, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('completed', $data['status']);
        self::assertSame('https://stackhal.com/', $data['target']);

        $requestStack->pop();
    }

    /**
     * @return array<string, mixed>
     */
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
        $fetcher = new class ($url, $html, $robotsTxt, $llmsTxt) extends SafeHttpFetcher {
            public function __construct(
                private readonly string $targetUrl,
                private readonly string $html,
                private readonly string $robotsTxt,
                private readonly string $llmsTxt,
            ) {
            }

            /**
             * @return array{requested_url:string,final_url:string,status:int,headers:array<string, list<string>>,body:string,content_type:string,duration_ms:int,redirects:list<array{url:string,status:int,location:?string}>,error:?string}
             */
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
