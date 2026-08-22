<?php

declare(strict_types=1);

namespace App\DomainInspector\Domain;

final readonly class MxCheck
{
    /**
     * @param list<array{host: string, priority: int}> $records
     */
    public function __construct(
        public bool $hasRecords,
        public array $records,
        public string $status,
        public string $summary,
        public ?string $recommendedFix = null,
    ) {
    }

    /**
     * @param list<array{target?: string, host?: string, prio?: int, priority?: int}> $mxRecords
     */
    public static function fromMxRecords(string $domain, array $mxRecords): self
    {
        if ($mxRecords === []) {
            return new self(
                hasRecords: false,
                records: [],
                status: 'fail',
                summary: 'No MX records configured. Domain cannot receive incoming email.',
                recommendedFix: sprintf('%s MX 10 mail.%s', $domain, $domain),
            );
        }

        $formatted = [];
        foreach ($mxRecords as $rec) {
            $host = $rec['target'] ?? $rec['host'] ?? '';
            $prio = $rec['prio'] ?? $rec['priority'] ?? 10;
            if ($host !== '') {
                $formatted[] = [
                    'host' => rtrim($host, '.'),
                    'priority' => (int) $prio,
                ];
            }
        }

        usort($formatted, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return new self(
            hasRecords: true,
            records: $formatted,
            status: 'pass',
            summary: sprintf('%d mail exchanger server(s) configured.', count($formatted)),
            recommendedFix: null,
        );
    }
}
