<?php

declare(strict_types=1);

namespace App\DomainInspector\Domain;

final readonly class DomainSecurityReport
{
    public int $score;
    public string $grade;
    public string $bimiReadiness;

    public function __construct(
        public string $domain,
        public string $inspectedAt,
        public DmarcCheck $dmarc,
        public BimiCheck $bimi,
        public MtaStsCheck $mtaSts,
        public TlsRptCheck $tlsRpt,
        public SpfCheck $spf,
        public MxCheck $mx,
    ) {
        $this->score = $this->calculateScore();
        $this->grade = $this->calculateGrade($this->score);
        $this->bimiReadiness = $this->calculateBimiReadiness();
    }

    private function calculateScore(): int
    {
        $score = 0;

        // DMARC (max 35)
        if ($this->dmarc->isBimiCompliant) {
            $score += 35;
        } elseif ($this->dmarc->hasRecord && ($this->dmarc->tags['p'] ?? '') === 'quarantine') {
            $score += 25;
        } elseif ($this->dmarc->hasRecord && ($this->dmarc->tags['p'] ?? '') === 'none') {
            $score += 15;
        }

        // BIMI (max 20)
        if ($this->bimi->hasRecord && $this->bimi->isSvgReachable) {
            $score += 20;
        } elseif ($this->bimi->hasRecord) {
            $score += 10;
        }

        // MTA-STS (max 20)
        if ($this->mtaSts->isPolicyFileReachable && $this->mtaSts->policyMode === 'enforce') {
            $score += 20;
        } elseif ($this->mtaSts->isPolicyFileReachable && $this->mtaSts->policyMode === 'testing') {
            $score += 15;
        } elseif ($this->mtaSts->hasDnsRecord) {
            $score += 8;
        }

        // TLS-RPT (max 10)
        if ($this->tlsRpt->hasRecord && $this->tlsRpt->rua !== null) {
            $score += 10;
        }

        // SPF (max 10)
        if ($this->spf->status === 'pass') {
            $score += 10;
        } elseif ($this->spf->allMechanism === '?all') {
            $score += 5;
        }

        // MX (max 5)
        if ($this->mx->hasRecords) {
            $score += 5;
        }

        return min(100, max(0, $score));
    }

    private function calculateGrade(int $score): string
    {
        return match (true) {
            $score >= 95 => 'A+',
            $score >= 80 => 'A',
            $score >= 65 => 'B',
            $score >= 50 => 'C',
            $score >= 30 => 'D',
            default => 'F',
        };
    }

    private function calculateBimiReadiness(): string
    {
        if ($this->dmarc->isBimiCompliant && $this->bimi->hasRecord && $this->bimi->isSvgReachable) {
            return 'ready';
        }

        if (!$this->dmarc->isBimiCompliant && $this->bimi->hasRecord) {
            return 'dmarc_upgrade_needed';
        }

        if ($this->dmarc->isBimiCompliant && !$this->bimi->hasRecord) {
            return 'eligible_missing_bimi';
        }

        return 'not_ready';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'inspected_at' => $this->inspectedAt,
            'score' => $this->score,
            'grade' => $this->grade,
            'bimi_readiness' => $this->bimiReadiness,
            'dmarc' => [
                'has_record' => $this->dmarc->hasRecord,
                'raw_record' => $this->dmarc->rawRecord,
                'status' => $this->dmarc->status,
                'summary' => $this->dmarc->summary,
                'is_bimi_compliant' => $this->dmarc->isBimiCompliant,
                'tags' => $this->dmarc->tags,
                'recommended_fix' => $this->dmarc->recommendedFix,
            ],
            'bimi' => [
                'has_record' => $this->bimi->hasRecord,
                'raw_record' => $this->bimi->rawRecord,
                'logo_url' => $this->bimi->logoUrl,
                'certificate_url' => $this->bimi->certificateUrl,
                'status' => $this->bimi->status,
                'summary' => $this->bimi->summary,
                'is_svg_reachable' => $this->bimi->isSvgReachable,
                'svg_content_type' => $this->bimi->svgContentType,
                'is_svg_tiny_ps' => $this->bimi->isSvgTinyPs,
                'recommended_fix' => $this->bimi->recommendedFix,
            ],
            'mta_sts' => [
                'has_dns_record' => $this->mtaSts->hasDnsRecord,
                'raw_dns_record' => $this->mtaSts->rawDnsRecord,
                'status' => $this->mtaSts->status,
                'summary' => $this->mtaSts->summary,
                'is_policy_file_reachable' => $this->mtaSts->isPolicyFileReachable,
                'policy_mode' => $this->mtaSts->policyMode,
                'policy_values' => $this->mtaSts->policyValues,
                'recommended_fix' => $this->mtaSts->recommendedFix,
            ],
            'tls_rpt' => [
                'has_record' => $this->tlsRpt->hasRecord,
                'raw_record' => $this->tlsRpt->rawRecord,
                'rua' => $this->tlsRpt->rua,
                'status' => $this->tlsRpt->status,
                'summary' => $this->tlsRpt->summary,
                'recommended_fix' => $this->tlsRpt->recommendedFix,
            ],
            'spf' => [
                'has_record' => $this->spf->hasRecord,
                'raw_record' => $this->spf->rawRecord,
                'all_mechanism' => $this->spf->allMechanism,
                'status' => $this->spf->status,
                'summary' => $this->spf->summary,
                'recommended_fix' => $this->spf->recommendedFix,
            ],
            'mx' => [
                'has_records' => $this->mx->hasRecords,
                'records' => $this->mx->records,
                'status' => $this->mx->status,
                'summary' => $this->mx->summary,
                'recommended_fix' => $this->mx->recommendedFix,
            ],
        ];
    }
}
