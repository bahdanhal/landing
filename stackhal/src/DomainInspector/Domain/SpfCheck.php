<?php

declare(strict_types=1);

namespace App\DomainInspector\Domain;

final readonly class SpfCheck
{
    /**
     * @param list<string> $mechanisms
     */
    public function __construct(
        public bool $hasRecord,
        public ?string $rawRecord,
        public array $mechanisms,
        public ?string $allMechanism,
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
        $spfRecords = [];
        foreach ($txtRecords as $record) {
            $trimmed = trim($record);
            if (str_starts_with(strtolower($trimmed), 'v=spf1') || str_starts_with(strtolower($trimmed), '"v=spf1')) {
                $spfRecords[] = trim($trimmed, '"');
            }
        }

        if ($spfRecords === []) {
            return new self(
                hasRecord: false,
                rawRecord: null,
                mechanisms: [],
                allMechanism: null,
                status: 'fail',
                summary: 'No SPF record (v=spf1) found on root domain.',
                recommendedFix: sprintf('%s TXT "v=spf1 include:_spf.google.com ~all"', $domain),
            );
        }

        if (count($spfRecords) > 1) {
            return new self(
                hasRecord: true,
                rawRecord: implode(' | ', $spfRecords),
                mechanisms: [],
                allMechanism: null,
                status: 'fail',
                summary: sprintf('Multiple SPF records found (%d records). RFC 7208 forbids publishing more than one SPF record.', count($spfRecords)),
                recommendedFix: 'Merge multiple SPF records into a single TXT record.',
            );
        }

        $record = $spfRecords[0];
        $tokens = preg_split('/\s+/', $record);
        $mechanisms = $tokens !== false ? array_slice($tokens, 1) : [];

        $allMechanism = null;
        foreach ($mechanisms as $mech) {
            $lower = strtolower($mech);
            if ($lower === '-all' || $lower === '~all' || $lower === '?all' || $lower === '+all' || $lower === 'all') {
                $allMechanism = $lower;
            }
        }

        $status = 'pass';
        $summary = '';
        $recommendedFix = null;

        if ($allMechanism === '-all') {
            $status = 'pass';
            $summary = 'Strict SPF policy (-all HardFail). Rejects unauthorized senders.';
        } elseif ($allMechanism === '~all') {
            $status = 'pass';
            $summary = 'Standard SPF policy (~all SoftFail). Valid for DMARC evaluation.';
        } elseif ($allMechanism === '?all') {
            $status = 'warning';
            $summary = 'Permissive SPF policy (?all Neutral). Does not provide strong anti-spoofing protection.';
            $recommendedFix = sprintf('Change ?all to ~all or -all in %s SPF record.', $domain);
        } elseif ($allMechanism === '+all' || $allMechanism === 'all') {
            $status = 'fail';
            $summary = 'Dangerous SPF policy (+all). Allows any server in the world to send mail as your domain!';
            $recommendedFix = sprintf('Change +all to ~all or -all in %s SPF record.', $domain);
        } else {
            $status = 'warning';
            $summary = 'SPF record found, but missing trailing all mechanism (~all or -all).';
            $recommendedFix = sprintf('Add ~all to the end of the SPF record for %s.', $domain);
        }

        return new self(
            hasRecord: true,
            rawRecord: $record,
            mechanisms: $mechanisms,
            allMechanism: $allMechanism,
            status: $status,
            summary: $summary,
            recommendedFix: $recommendedFix,
        );
    }
}
