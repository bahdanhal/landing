<?php

declare(strict_types=1);

namespace App\Tests\DomainInspector;

use App\DomainInspector\Application\DnsResolverInterface;
use App\DomainInspector\Application\DomainInspector;
use App\Shared\Infrastructure\Http\SafeHttpFetcher;
use App\Shared\Infrastructure\Http\UrlGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DomainInspectorTest extends TestCase
{
    public function testNormalizeDomain(): void
    {
        $resolver = $this->createStub(DnsResolverInterface::class);
        $fetcher = new SafeHttpFetcher(new MockHttpClient(), new UrlGuard(), 5, 1048576);
        $inspector = new DomainInspector($resolver, $fetcher);

        self::assertSame('example.com', $inspector->normalizeDomain('https://example.com/some/path?query=1'));
        self::assertSame('sub.domain.org', $inspector->normalizeDomain('http://sub.domain.org:8080/'));
        self::assertSame('stripe.com', $inspector->normalizeDomain('  STRIPE.COM.  '));
    }

    public function testInvalidDomainThrows(): void
    {
        $resolver = $this->createStub(DnsResolverInterface::class);
        $fetcher = new SafeHttpFetcher(new MockHttpClient(), new UrlGuard(), 5, 1048576);
        $inspector = new DomainInspector($resolver, $fetcher);

        $this->expectException(\InvalidArgumentException::class);
        $inspector->normalizeDomain('localhost');
    }

    public function testFullDomainInspection(): void
    {
        $resolver = $this->createStub(DnsResolverInterface::class);
        $resolver->method('getTxtRecords')->willReturnCallback(static function (string $hostname): array {
            return match ($hostname) {
                '_dmarc.example.com' => ['v=DMARC1; p=reject; pct=100; rua=mailto:dmarc@example.com;'],
                'default._bimi.example.com' => ['v=BIMI1; l=https://example.com/logo-bimi.svg;'],
                '_mta-sts.example.com' => ['v=STSv1; id=202608230000;'],
                '_smtp._tls.example.com' => ['v=TLSRPTv1; rua=mailto:tls@example.com;'],
                'example.com' => ['v=spf1 include:_spf.google.com ~all'],
                default => [],
            };
        });

        $resolver->method('getMxRecords')->willReturn([
            ['host' => 'mail.example.com', 'priority' => 10],
        ]);

        $fetcher = $this->createStub(SafeHttpFetcher::class);
        $fetcher->method('fetch')->willReturnCallback(static function (string $url): array {
            if (str_ends_with($url, '.svg')) {
                return [
                    'status' => 200,
                    'content_type' => 'image/svg+xml',
                    'body' => '<svg xmlns="http://www.w3.org/2000/svg" version="1.2" baseProfile="tiny-ps" viewBox="0 0 512 512"></svg>',
                    'error' => null,
                    'requested_url' => $url,
                    'final_url' => $url,
                    'headers' => [],
                    'duration_ms' => 10,
                    'redirects' => [],
                ];
            }

            return [
                'status' => 200,
                'content_type' => 'text/plain',
                'body' => "version: STSv1\nmode: enforce\nmx: mail.example.com\nmax_age: 604800\n",
                'error' => null,
                'requested_url' => $url,
                'final_url' => $url,
                'headers' => [],
                'duration_ms' => 10,
                'redirects' => [],
            ];
        });

        $inspector = new DomainInspector($resolver, $fetcher);

        $report = $inspector->inspect('example.com');

        self::assertSame('example.com', $report->domain);
        self::assertTrue($report->dmarc->isBimiCompliant);
        self::assertTrue($report->bimi->isSvgReachable);
        self::assertSame('ready', $report->bimiReadiness);
        self::assertSame(100, $report->score);
        self::assertSame('A+', $report->grade);

        $array = $report->toArray();
        self::assertSame(100, $array['score']);
        self::assertSame('ready', $array['bimi_readiness']);
    }
}
