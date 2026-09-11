<?php

declare(strict_types=1);

namespace App\Tests\Analytics;

use App\Analytics\Domain\PageViewRepository;
use App\Analytics\Presentation\Http\BeaconController;
use Bahdan\PrivacyAnalyticsBundle\Domain\PageView;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class BeaconControllerTest extends TestCase
{
    public function testOptionsRequestReturnsNoContentWithCorsHeaders(): void
    {
        $repository = $this->createMock(PageViewRepository::class);
        $repository->expects(self::never())->method('save');

        $controller = new BeaconController($repository, 'analytics-secret');
        $request = Request::create('https://bahdanhal.pl/api/pa/hit', 'OPTIONS');

        $response = $controller($request);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertStringContainsString('POST', (string) $response->headers->get('Access-Control-Allow-Methods'));
    }

    public function testValidBeaconRecordsPageView(): void
    {
        $stored = null;
        $repository = $this->createMock(PageViewRepository::class);
        $repository->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (PageView $pv) use (&$stored): void {
                $stored = $pv;
            });

        $controller = new BeaconController($repository, 'analytics-secret');
        $payload = json_encode(['p' => '/services', 'r' => 'https://google.com/search?q=consulting'], JSON_THROW_ON_ERROR);
        $request = Request::create(
            'https://bahdanhal.pl/api/pa/hit',
            'POST',
            server: [
                'REMOTE_ADDR' => '198.51.100.8',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
                'HTTP_ACCEPT' => '*/*',
                'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
                'HTTP_SEC_FETCH_MODE' => 'no-cors',
                'HTTP_SEC_CH_UA' => '"Chromium";v="123", "Not A(Brand";v="24", "Google Chrome";v="123"',
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $payload,
        );

        $response = $controller($request);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertInstanceOf(PageView::class, $stored);
        self::assertSame('/services', $stored->path);
        self::assertSame('search', $stored->source);
        self::assertSame('google.com', $stored->referrerHost);
        $expectedHash = hash_hmac(
            'sha256',
            gmdate('Y-m-d') . '|198.51.100.8|Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
                . '(KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
            'analytics-secret',
        );
        self::assertSame($expectedHash, $stored->visitorHash);
    }

    public function testExcludesDntAndGpcHeaders(): void
    {
        $repository = $this->createMock(PageViewRepository::class);
        $repository->expects(self::never())->method('save');

        $controller = new BeaconController($repository, 'analytics-secret');
        $payload = json_encode(['p' => '/'], JSON_THROW_ON_ERROR);
        $request = Request::create(
            'https://bahdanhal.pl/api/pa/hit',
            'POST',
            server: [
                'REMOTE_ADDR' => '198.51.100.8',
                'HTTP_USER_AGENT' => 'Mozilla/5.0',
                'HTTP_ACCEPT_LANGUAGE' => 'en-US',
                'HTTP_DNT' => '1',
            ],
            content: $payload,
        );

        $response = $controller($request);
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testExcludesStealthScraperGreaseHeaders(): void
    {
        $repository = $this->createMock(PageViewRepository::class);
        $repository->expects(self::never())->method('save');

        $controller = new BeaconController($repository, 'analytics-secret');
        $payload = json_encode(['p' => '/services'], JSON_THROW_ON_ERROR);
        $request = Request::create(
            'https://bahdanhal.pl/api/pa/hit',
            'POST',
            server: [
                'REMOTE_ADDR' => '47.79.39.102',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                'HTTP_ACCEPT' => '*/*',
                'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
                'HTTP_SEC_CH_UA' => '"Not-A.Brand";v="99", "Chromium";v="144", "Google Chrome";v="144"',
            ],
            content: $payload,
        );

        $response = $controller($request);
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testExcludesAdminPath(): void
    {
        $repository = $this->createMock(PageViewRepository::class);
        $repository->expects(self::never())->method('save');

        $controller = new BeaconController($repository, 'analytics-secret');
        $payload = json_encode(['p' => '/admin/login'], JSON_THROW_ON_ERROR);
        $request = Request::create(
            'https://bahdanhal.pl/api/pa/hit',
            'POST',
            server: [
                'REMOTE_ADDR' => '198.51.100.8',
                'HTTP_USER_AGENT' => 'Mozilla/5.0',
                'HTTP_ACCEPT_LANGUAGE' => 'en-US',
            ],
            content: $payload,
        );

        $response = $controller($request);
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
