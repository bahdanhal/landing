<?php

declare(strict_types=1);

namespace App\DomainInspector\Domain;

final readonly class TlsRptCheck
{
    /**
     * @param array<string, string> $tags
     */
    public function __construct(
        public bool $hasRecord,
        public ?string $rawRecord,
        public array $tags,
        public ?string $rua,
        public string $status,
        public string $summary,
        public ?string $recommendedFix = null,
    ) {
    }

    /**
     * @param list<string> $txtRecords
     */
    public static function fromTxtRecords(string $domain, array $txtRecords): self
    {
        $tlsRecord = null;
        foreach ($txtRecords as $record) {
            $trimmed = trim($record);
            if (str_starts_with(strtoupper($trimmed), 'V=TLSRPTV1') || str_starts_with(strtoupper($trimmed), '"V=TLSRPTV1')) {
                $tlsRecord = trim($trimmed, '"');
                break;
            }
        }

        if ($tlsRecord === null) {
            return new self(
                hasRecord: false,
                rawRecord: null,
                tags: [],
                rua: null,
                status: 'fail',
                summary: 'No SMTP TLS-RPT record found at _smtp._tls.' . $domain . ' (RFC 8460).',
                recommendedFix: sprintf('_smtp._tls.%s TXT "v=TLSRPTv1; rua=mailto:tls-reports@%s;"', $domain, $domain),
            );
        }

        $tags = self::parseTags($tlsRecord);
        $rua = $tags['rua'] ?? null;

        if ($rua === null || $rua === '') {
            return new self(
                hasRecord: true,
                rawRecord: $tlsRecord,
                tags: $tags,
                rua: null,
                status: 'warning',
                summary: 'TLS-RPT record exists but is missing the "rua=" report recipient.',
                recommendedFix: sprintf('_smtp._tls.%s TXT "v=TLSRPTv1; rua=mailto:tls-reports@%s;"', $domain, $domain),
            );
        }

        return new self(
            hasRecord: true,
            rawRecord: $tlsRecord,
            tags: $tags,
            rua: $rua,
            status: 'pass',
            summary: sprintf('SMTP TLS-RPT is active with report recipient: %s', $rua),
            recommendedFix: null,
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
}
