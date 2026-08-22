<?php

declare(strict_types=1);

namespace App\DomainInspector\Domain;

final readonly class BimiCheck
{
    /**
     * @param array<string, string> $tags
     */
    public function __construct(
        public bool $hasRecord,
        public ?string $rawRecord,
        public array $tags,
        public ?string $logoUrl,
        public ?string $certificateUrl,
        public string $status,
        public string $summary,
        public bool $isSvgReachable,
        public ?string $svgContentType = null,
        public bool $isSvgTinyPs = false,
        public ?string $recommendedFix = null,
    ) {
    }

    /**
     * @param list<string> $txtRecords
     */
    public static function fromTxtRecords(
        string $domain,
        array $txtRecords,
        bool $isSvgReachable = false,
        ?string $svgContentType = null,
        bool $isSvgTinyPs = false,
    ): self {
        $bimiRecord = null;
        foreach ($txtRecords as $record) {
            $trimmed = trim($record);
            if (str_starts_with(strtoupper($trimmed), 'V=BIMI1') || str_starts_with(strtoupper($trimmed), '"V=BIMI1')) {
                $bimiRecord = trim($trimmed, '"');
                break;
            }
        }

        if ($bimiRecord === null) {
            return new self(
                hasRecord: false,
                rawRecord: null,
                tags: [],
                logoUrl: null,
                certificateUrl: null,
                status: 'fail',
                summary: 'No BIMI DNS record found at default._bimi.' . $domain,
                isSvgReachable: false,
                recommendedFix: sprintf('default._bimi.%s TXT "v=BIMI1; l=https://%s/logo-bimi.svg;"', $domain, $domain),
            );
        }

        $tags = self::parseTags($bimiRecord);
        $logoUrl = $tags['l'] ?? null;
        $certificateUrl = $tags['a'] ?? null;

        if ($logoUrl === null || $logoUrl === '') {
            return new self(
                hasRecord: true,
                rawRecord: $bimiRecord,
                tags: $tags,
                logoUrl: null,
                certificateUrl: $certificateUrl,
                status: 'warning',
                summary: 'BIMI record exists but missing "l=" logo URL tag.',
                isSvgReachable: false,
                recommendedFix: sprintf('default._bimi.%s TXT "v=BIMI1; l=https://%s/logo-bimi.svg;"', $domain, $domain),
            );
        }

        if (!str_starts_with(strtolower($logoUrl), 'https://')) {
            return new self(
                hasRecord: true,
                rawRecord: $bimiRecord,
                tags: $tags,
                logoUrl: $logoUrl,
                certificateUrl: $certificateUrl,
                status: 'warning',
                summary: 'BIMI logo URL must use HTTPS.',
                isSvgReachable: false,
                recommendedFix: sprintf('default._bimi.%s TXT "v=BIMI1; l=https://%s/logo-bimi.svg;"', $domain, $domain),
            );
        }

        $status = 'pass';
        $summary = 'Valid BIMI record found.';
        if ($isSvgReachable) {
            $summary .= ' SVG logo is reachable over HTTPS.';
            if ($isSvgTinyPs) {
                $summary .= ' Conforms to SVG Tiny 1.2 PS.';
            }
        } else {
            $status = 'warning';
            $summary .= ' SVG logo could not be verified at ' . $logoUrl;
        }

        return new self(
            hasRecord: true,
            rawRecord: $bimiRecord,
            tags: $tags,
            logoUrl: $logoUrl,
            certificateUrl: $certificateUrl,
            status: $status,
            summary: $summary,
            isSvgReachable: $isSvgReachable,
            svgContentType: $svgContentType,
            isSvgTinyPs: $isSvgTinyPs,
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
