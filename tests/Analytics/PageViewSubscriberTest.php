<?php

declare(strict_types=1);

namespace App\Tests\Analytics;

use App\Analytics\Domain\PageView;
use App\Analytics\Domain\PageViewRepository;
use App\Analytics\Infrastructure\PageViewSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class PageViewSubscriberTest extends TestCase
{
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
        self::assertSame(hash_hmac('sha256', '198.51.100.8', 'analytics-secret'), $stored->visitorHash);
    }

    public function testExcludesBotsAndPrivacySignals(): void
    {
        $repository = $this->createMock(PageViewRepository::class);
        $repository->expects(self::never())->method('save');
        $request = Request::create('https://bahdanhal.pl/', 'GET', server: [
            'REMOTE_ADDR' => '198.51.100.8',
            'HTTP_USER_AGENT' => 'ExampleBot/1.0',
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
}
