<?php

declare(strict_types=1);

namespace App\DomainInspector\Infrastructure;

use App\DomainInspector\Application\DnsResolverInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class CachedDnsResolver implements DnsResolverInterface
{
    private const DEFAULT_TTL = 300;

    public function __construct(
        private DnsResolverInterface $delegate,
        private ?CacheInterface $cache = null,
        private int $ttl = self::DEFAULT_TTL,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getTxtRecords(string $hostname): array
    {
        if ($this->cache === null) {
            return $this->delegate->getTxtRecords($hostname);
        }

        $key = 'dns_txt_' . md5(strtolower(trim($hostname)));

        /** @var list<string> */
        return $this->cache->get($key, function (ItemInterface $item) use ($hostname): array {
            $item->expiresAfter($this->ttl);
            return $this->delegate->getTxtRecords($hostname);
        });
    }

    /**
     * @return list<array{host?: string, target?: string, prio?: int, priority?: int}>
     */
    public function getMxRecords(string $hostname): array
    {
        if ($this->cache === null) {
            return $this->delegate->getMxRecords($hostname);
        }

        $key = 'dns_mx_' . md5(strtolower(trim($hostname)));

        /** @var list<array{host?: string, target?: string, prio?: int, priority?: int}> */
        return $this->cache->get($key, function (ItemInterface $item) use ($hostname): array {
            $item->expiresAfter($this->ttl);
            return $this->delegate->getMxRecords($hostname);
        });
    }
}
