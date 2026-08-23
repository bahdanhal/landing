<?php

declare(strict_types=1);

namespace App\Tests\DomainInspector;

use App\DomainInspector\Application\DnsResolverInterface;
use App\DomainInspector\Infrastructure\CachedDnsResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachedDnsResolverTest extends TestCase
{
    public function testCachesTxtRecordLookups(): void
    {
        $delegate = $this->createMock(DnsResolverInterface::class);
        $delegate->expects(self::once())
            ->method('getTxtRecords')
            ->with('example.com')
            ->willReturn(['v=spf1 ~all']);

        $cache = new ArrayAdapter();
        $resolver = new CachedDnsResolver($delegate, $cache, 300);

        $first = $resolver->getTxtRecords('example.com');
        $second = $resolver->getTxtRecords('example.com');

        self::assertSame(['v=spf1 ~all'], $first);
        self::assertSame(['v=spf1 ~all'], $second);
    }

    public function testCachesMxRecordLookups(): void
    {
        $delegate = $this->createMock(DnsResolverInterface::class);
        $delegate->expects(self::once())
            ->method('getMxRecords')
            ->with('example.com')
            ->willReturn([['host' => 'mail.example.com', 'priority' => 10]]);

        $cache = new ArrayAdapter();
        $resolver = new CachedDnsResolver($delegate, $cache, 300);

        $first = $resolver->getMxRecords('example.com');
        $second = $resolver->getMxRecords('example.com');

        self::assertSame([['host' => 'mail.example.com', 'priority' => 10]], $first);
        self::assertSame([['host' => 'mail.example.com', 'priority' => 10]], $second);
    }

    public function testWorksWithoutCache(): void
    {
        $delegate = $this->createMock(DnsResolverInterface::class);
        $delegate->expects(self::exactly(2))
            ->method('getTxtRecords')
            ->with('example.com')
            ->willReturn(['v=spf1 ~all']);

        $resolver = new CachedDnsResolver($delegate, null);

        $first = $resolver->getTxtRecords('example.com');
        $second = $resolver->getTxtRecords('example.com');

        self::assertSame(['v=spf1 ~all'], $first);
        self::assertSame(['v=spf1 ~all'], $second);
    }
}
