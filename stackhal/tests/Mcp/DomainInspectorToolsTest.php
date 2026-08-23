<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\DomainInspector\Application\DnsResolverInterface;
use App\DomainInspector\Application\DomainInspector;
use App\Mcp\DomainInspectorTools;
use App\Shared\Infrastructure\Http\SafeHttpFetcher;
use App\Shared\Infrastructure\Http\UrlGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class DomainInspectorToolsTest extends TestCase
{
    public function testInspectDomainSuccess(): void
    {
        $resolver = $this->createStub(DnsResolverInterface::class);
        $resolver->method('getTxtRecords')->willReturnCallback(static function (string $hostname): array {
            return match ($hostname) {
                '_dmarc.stripe.com' => ['v=DMARC1; p=reject; pct=100; rua=mailto:dmarc@stripe.com;'],
                'stripe.com' => ['v=spf1 include:_spf.google.com ~all'],
                default => [],
            };
        });
        $resolver->method('getMxRecords')->willReturn([
            ['host' => 'mail.stripe.com', 'priority' => 10],
        ]);

        $fetcher = new SafeHttpFetcher(new MockHttpClient(), new UrlGuard(), 5, 1048576);
        $inspector = new DomainInspector($resolver, $fetcher);
        $tools = new DomainInspectorTools($inspector);

        $json = $tools->inspectDomain('stripe.com');
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('completed', $data['status']);
        self::assertSame('stripe.com', $data['report']['domain']);
        self::assertTrue($data['report']['dmarc']['is_bimi_compliant']);
    }

    public function testInspectDomainInvalidDomainReturnsError(): void
    {
        $resolver = $this->createStub(DnsResolverInterface::class);
        $fetcher = new SafeHttpFetcher(new MockHttpClient(), new UrlGuard(), 5, 1048576);
        $inspector = new DomainInspector($resolver, $fetcher);
        $tools = new DomainInspectorTools($inspector);

        $json = $tools->inspectDomain('localhost');
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('error', $data['status']);
        self::assertArrayHasKey('error', $data);
    }
}
