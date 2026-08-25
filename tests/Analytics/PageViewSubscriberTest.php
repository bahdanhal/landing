<?php

declare(strict_types=1);

namespace App\Tests\Analytics;

use Bahdan\PrivacyAnalyticsBundle\Domain\PageView;
use App\Analytics\Domain\PageViewRepository;
use App\Analytics\Infrastructure\PageViewSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class PageViewSubscriberTest extends TestCase
{
    public function testRunsAfterSymfonyResponseNormalization(): void
    {
        self::assertSame(
            [KernelEvents::RESPONSE => ['onResponse', -10]],
            PageViewSubscriber::getSubscribedEvents(),
        );
    }

    public function testStoresOnlyPrivacyPreservingPageViewData(): void
    {
        $stored = null;
        $repository = $this->createMock(PageViewRepository::class);
        $repository->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (PageView $pageView) use (&$stored): void {
                $stored = $pageView;
            });
        $request = Request::create(
            'https://bahdanhal.pl/tools?email=private@example.com',
            'GET',
            server: [
                'REMOTE_ADDR' => '198.51.100.8',
                'HTTP_REFERER' => 'https://www.google.com/search?q=private',
                'HTTP_USER_AGENT' => 'Mozilla/5.0',
            ],
        );
        $response = new Response('<html></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        (new PageViewSubscriber($repository, 'analytics-secret'))->onResponse($event);

        self::assertInstanceOf(PageView::class, $stored);
        self::assertSame('/tools', $stored->path);
        self::assertSame('search', $stored->source);
        self::assertSame('google.com', $stored->referrerHost);
        self::assertSame(hash_hmac('sha256', '198.51.100.8|Mozilla/5.0', 'analytics-secret'), $stored->visitorHash);
    }

    #[DataProvider('provideExcludedUserAgents')]
    public function testExcludesBotsAndPrivacySignals(string $userAgent): void
    {
        $repository = $this->createMock(PageViewRepository::class);
        $repository->expects(self::never())->method('save');
        $request = Request::create('https://bahdanhal.pl/', 'GET', server: [
            'REMOTE_ADDR' => '198.51.100.8',
            'HTTP_USER_AGENT' => $userAgent,
        ]);
        $response = new Response('<html></html>', 200, ['Content-Type' => 'text/html']);
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        (new PageViewSubscriber($repository, 'analytics-secret'))->onResponse($event);
    }

    /**
     * @return list<array{string}>
     */
    public static function provideExcludedUserAgents(): array
    {
        return [
            [''],
            ['ExampleBot/1.0'],
            ['BahdanToolbox/1.0'],
            ['Google-InspectionTool/1.0'],
            ['Mozilla/5.0 CMS-Checker/1.0'],
            ['crt-indexer/1.0'],
            ['DomainIntelCollector/1.0'],
            ['SparixEmailScraper/1.0'],
            ['WP-Safe-Scanner/1.0'],
            ['InternetMeasurement/1.0'],
            ['Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15'],
            ['Mozilla/5.0 Chrome/148.0.0.0 Safari/537.36'],
            ['curl/7.68.0'],
            ['python-requests/2.28.1'],
            ['GuzzleHttp/7'],
            ['Go-http-client/1.1'],
            ['PostmanRuntime/7.26.8'],
            ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/100.0.4896.60 Safari/537.36'],
        ];
    }

    #[DataProvider('provideProbePaths')]
    public function testExcludesSecurityProbePaths(string $path): void
    {
        $repository = $this->createMock(PageViewRepository::class);
        $repository->expects(self::never())->method('save');
        $request = Request::create('https://bahdanhal.pl' . $path, 'GET', server: [
            'REMOTE_ADDR' => '198.51.100.8',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ]);
        $response = new Response('<html></html>', 200, ['Content-Type' => 'text/html']);
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        (new PageViewSubscriber($repository, 'analytics-secret'))->onResponse($event);
    }

    /** @return list<array{string}> */
    public static function provideProbePaths(): array
    {
        return [
            ['/wp-content/themes/index.php'],
            ['/wp-admin/index.php'],
            ['/.env'],
            ['/.git/HEAD'],
        ];
    }
}
