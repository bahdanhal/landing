<?php

declare(strict_types=1);

namespace App\DomainInspector\Infrastructure;

use App\DomainInspector\Application\DnsResolverInterface;

final readonly class NativeDnsResolver implements DnsResolverInterface
{
    /**
     * @return list<string>
     */
    public function getTxtRecords(string $hostname): array
    {
        error_clear_last();
        $raw = @dns_get_record($hostname, DNS_TXT);
        if ($raw === false || $raw === []) {
            return [];
        }

        $records = [];
        foreach ($raw as $entry) {
            if (isset($entry['txt']) && is_string($entry['txt'])) {
                $records[] = $entry['txt'];
            } elseif (isset($entry['entries']) && is_array($entry['entries'])) {
                $records[] = implode('', $entry['entries']);
            }
        }

        return $records;
    }

    /**
     * @return list<array{host?: string, target?: string, prio?: int, priority?: int}>
     */
    public function getMxRecords(string $hostname): array
    {
        error_clear_last();
        $raw = @dns_get_record($hostname, DNS_MX);
        if ($raw === false || $raw === []) {
            return [];
        }

        /** @var list<array{host?: string, target?: string, prio?: int, priority?: int}> $records */
        $records = $raw;

        return $records;
    }
}
