<?php

declare(strict_types=1);

namespace App\DomainInspector\Application;

interface DnsResolverInterface
{
    /**
     * @return list<string>
     */
    public function getTxtRecords(string $hostname): array;

    /**
     * @return list<array{host?: string, target?: string, prio?: int, priority?: int}>
     */
    public function getMxRecords(string $hostname): array;
}
