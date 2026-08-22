<?php

declare(strict_types=1);

namespace App\DomainInspector\Domain;

final readonly class MtaStsCheck
{
    /**
     * @param array<string, string> $tags
     * @param array<string, string|list<string>> $policyValues
     */
    public function __construct(
        public bool $hasDnsRecord,
        public ?string $rawDnsRecord,
        public array $tags,
        public string $status,
        public string $summary,
        public bool $isPolicyFileReachable,
        public ?string $policyMode = null,
        public array $policyValues = [],
        public ?string $recommendedFix = null,
    ) {
    }

    /**
     * @param list<string> $txtRecords
     * @param ?string $policyFileContent
     */
    public static function evaluate(
        string $domain,
        array $txtRecords,
        bool $isPolicyFileReachable = false,
        ?string $policyFileContent = null,
    ): self {
        $stsRecord = null;
        foreach ($txtRecords as $record) {
            $trimmed = trim($record);
            if (str_starts_with(strtoupper($trimmed), 'V=STSV1') || str_starts_with(strtoupper($trimmed), '"V=STSV1')) {
                $stsRecord = trim($trimmed, '"');
                break;
            }
        }

        if ($stsRecord === null) {
            return new self(
                hasDnsRecord: false,
                rawDnsRecord: null,
                tags: [],
                status: 'fail',
                summary: 'No MTA-STS DNS record found at _mta-sts.' . $domain . ' (RFC 8461).',
                isPolicyFileReachable: false,
                policyMode: null,
                policyValues: [],
                recommendedFix: sprintf('_mta-sts.%s TXT "v=STSv1; id=%s;"', $domain, gmdate('YmdHis')),
            );
        }

        $tags = self::parseTags($stsRecord);
        $id = $tags['id'] ?? null;

        $parsedPolicy = [];
        $policyMode = null;
        if ($isPolicyFileReachable && $policyFileContent !== null) {
            $parsedPolicy = self::parsePolicyFile($policyFileContent);
            $policyMode = isset($parsedPolicy['mode']) && is_string($parsedPolicy['mode']) ? strtolower($parsedPolicy['mode']) : null;
        }

        $status = 'pass';
        $summary = '';
        $recommendedFix = null;

        if ($isPolicyFileReachable && $policyMode === 'enforce') {
            $status = 'pass';
            $summary = 'MTA-STS is active and enforced (mode: enforce). Protects SMTP from TLS downgrade and MITM attacks.';
        } elseif ($isPolicyFileReachable && $policyMode === 'testing') {
            $status = 'pass';
            $summary = 'MTA-STS is in testing mode (mode: testing). Policy is published and receiving reports.';
        } elseif ($isPolicyFileReachable) {
            $status = 'warning';
            $summary = sprintf('MTA-STS policy file reachable but mode is "%s".', $policyMode ?? 'unknown');
        } else {
            $status = 'warning';
            $summary = sprintf(
                'MTA-STS DNS record exists (id=%s), but https://mta-sts.%s/.well-known/mta-sts.txt could not be fetched.',
                $id ?? 'unknown',
                $domain
            );
            $recommendedFix = sprintf(
                'Deploy https://mta-sts.%s/.well-known/mta-sts.txt with "version: STSv1\nmode: enforce\nmx: %s\nmax_age: 604800"',
                $domain,
                $domain
            );
        }

        return new self(
            hasDnsRecord: true,
            rawDnsRecord: $stsRecord,
            tags: $tags,
            status: $status,
            summary: $summary,
            isPolicyFileReachable: $isPolicyFileReachable,
            policyMode: $policyMode,
            policyValues: $parsedPolicy,
            recommendedFix: $recommendedFix,
        );
    }

    /**
     * @return array<string, string>
     */
    private static function parseTags(string $raw): array
    {
        $tags = [];
        $parts = explode(';', $raw);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                $tags[trim($kv[0])] = trim($kv[1]);
            }
        }

        return $tags;
    }

    /**
     * @return array<string, string|list<string>>
     */
    private static function parsePolicyFile(string $content): array
    {
        $result = [];
        $lines = preg_split('/\r\n|\r|\n/', $content);
        if ($lines === false) {
            return [];
        }

        $mxList = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $key = strtolower(trim($parts[0]));
                $val = trim($parts[1]);
                if ($key === 'mx') {
                    $mxList[] = $val;
                } else {
                    $result[$key] = $val;
                }
            }
        }

        if ($mxList !== []) {
            $result['mx'] = $mxList;
        }

        return $result;
    }
}
