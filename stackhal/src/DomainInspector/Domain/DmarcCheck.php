<?php

declare(strict_types=1);

namespace App\DomainInspector\Domain;

final readonly class DmarcCheck
{
    /**
     * @param array<string, string> $tags
     */
    public function __construct(
        public bool $hasRecord,
        public ?string $rawRecord,
        public array $tags,
        public string $status,
        public string $summary,
        public bool $isBimiCompliant,
        public ?string $recommendedFix = null,
    ) {
    }

    /**
     * @param list<string> $txtRecords
     */
    public static function fromTxtRecords(string $domain, array $txtRecords): self
    {
        $dmarcRecord = null;
        foreach ($txtRecords as $record) {
            $trimmed = trim($record);
            if (str_starts_with(strtoupper($trimmed), 'V=DMARC1') || str_starts_with(strtoupper($trimmed), '"V=DMARC1')) {
                $dmarcRecord = trim($trimmed, '"');
                break;
            }
        }

        if ($dmarcRecord === null) {
            return new self(
                hasRecord: false,
                rawRecord: null,
                tags: [],
                status: 'fail',
                summary: 'No DMARC record found at _dmarc.' . $domain,
                isBimiCompliant: false,
                recommendedFix: sprintf('_dmarc.%s TXT "v=DMARC1; p=reject; pct=100; rua=mailto:dmarc-reports@%s;"', $domain, $domain),
            );
        }

        $tags = self::parseTags($dmarcRecord);
        $policy = strtolower($tags['p'] ?? '');
        $subdomainPolicy = isset($tags['sp']) ? strtolower($tags['sp']) : null;
        $pct = isset($tags['pct']) && is_numeric($tags['pct']) ? (int) $tags['pct'] : 100;
        $rua = $tags['rua'] ?? null;

        $isBimiCompliant = false;
        $status = 'pass';
        $summary = '';
        $recommendedFix = null;

        if ($policy === 'reject') {
            if ($pct === 100 && ($subdomainPolicy === null || $subdomainPolicy === 'reject' || $subdomainPolicy === 'quarantine')) {
                $isBimiCompliant = true;
                $status = 'pass';
                $summary = 'Strict DMARC policy (p=reject, pct=100). Full BIMI eligibility.';
            } else {
                $status = 'warning';
                $summary = sprintf('DMARC policy is reject but pct=%d%% or sp=%s.', $pct, $subdomainPolicy ?? 'inherit');
                $recommendedFix = sprintf('_dmarc.%s TXT "v=DMARC1; p=reject; pct=100; rua=%s;"', $domain, $rua ?? 'mailto:dmarc@' . $domain);
            }
        } elseif ($policy === 'quarantine') {
            if ($pct === 100 && ($subdomainPolicy === null || $subdomainPolicy === 'reject' || $subdomainPolicy === 'quarantine')) {
                $isBimiCompliant = true;
                $status = 'pass';
                $summary = 'DMARC policy is quarantine with pct=100. BIMI compliant.';
            } else {
                $status = 'warning';
                $summary = sprintf('DMARC policy is quarantine, but pct is %d%% (BIMI requires pct=100).', $pct);
                $recommendedFix = sprintf('_dmarc.%s TXT "v=DMARC1; p=quarantine; pct=100; rua=%s;"', $domain, $rua ?? 'mailto:dmarc@' . $domain);
            }
        } elseif ($policy === 'none') {
            $status = 'warning';
            $summary = 'DMARC policy is p=none (monitoring only). '
                . 'Mailboxes will not display BIMI avatars until upgraded to p=quarantine (pct=100) or p=reject.';
            $recommendedFix = sprintf('_dmarc.%s TXT "v=DMARC1; p=quarantine; pct=100; rua=%s;"', $domain, $rua ?? 'mailto:dmarc@' . $domain);
        } else {
            $status = 'fail';
            $summary = 'Invalid or missing "p=" tag in DMARC record.';
            $recommendedFix = sprintf('_dmarc.%s TXT "v=DMARC1; p=reject; pct=100; rua=mailto:dmarc@%s;"', $domain, $domain);
        }

        return new self(
            hasRecord: true,
            rawRecord: $dmarcRecord,
            tags: $tags,
            status: $status,
            summary: $summary,
            isBimiCompliant: $isBimiCompliant,
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
}
