<?php

declare(strict_types=1);

namespace App\DomainInspector\Application;

use App\DomainInspector\Domain\BimiCheck;
use App\DomainInspector\Domain\DmarcCheck;
use App\DomainInspector\Domain\DomainSecurityReport;
use App\DomainInspector\Domain\MtaStsCheck;
use App\DomainInspector\Domain\MxCheck;
use App\DomainInspector\Domain\SpfCheck;
use App\DomainInspector\Domain\TlsRptCheck;
use App\Shared\Infrastructure\Http\SafeHttpFetcher;

final readonly class DomainInspector
{
    public function __construct(
        private DnsResolverInterface $dnsResolver,
        private SafeHttpFetcher $httpFetcher,
    ) {
    }

    public function inspect(string $domainInput): DomainSecurityReport
    {
        $domain = $this->normalizeDomain($domainInput);

        // 1. DMARC check
        $dmarcTxt = $this->dnsResolver->getTxtRecords('_dmarc.' . $domain);
        $dmarcCheck = DmarcCheck::fromTxtRecords($domain, $dmarcTxt);

        // 2. BIMI check
        $bimiTxt = $this->dnsResolver->getTxtRecords('default._bimi.' . $domain);
        $bimiPreliminary = BimiCheck::fromTxtRecords($domain, $bimiTxt);

        $isSvgReachable = false;
        $svgContentType = null;
        $isSvgTinyPs = false;

        if ($bimiPreliminary->hasRecord && $bimiPreliminary->logoUrl !== null && str_starts_with($bimiPreliminary->logoUrl, 'https://')) {
            try {
                $response = $this->httpFetcher->fetch($bimiPreliminary->logoUrl);
                if ($response['status'] === 200 && $response['error'] === null) {
                    $isSvgReachable = true;
                    $svgContentType = $response['content_type'];
                    $body = $response['body'];
                    if (
                        str_contains($body, '<svg')
                        && (str_contains($body, 'baseProfile="tiny-ps"') || str_contains($body, 'version="1.2"'))
                    ) {
                        $isSvgTinyPs = true;
                    }
                }
            } catch (\Throwable) {
                // If fetching fails or SSRF guard rejects, record as unreachable
                $isSvgReachable = false;
            }
        }

        $bimiCheck = BimiCheck::fromTxtRecords(
            $domain,
            $bimiTxt,
            isSvgReachable: $isSvgReachable,
            svgContentType: $svgContentType,
            isSvgTinyPs: $isSvgTinyPs,
        );

        // 3. MTA-STS check
        $mtaStsTxt = $this->dnsResolver->getTxtRecords('_mta-sts.' . $domain);
        $isPolicyFileReachable = false;
        $policyFileContent = null;

        if ($mtaStsTxt !== []) {
            $policyUrl = 'https://mta-sts.' . $domain . '/.well-known/mta-sts.txt';
            try {
                $response = $this->httpFetcher->fetch($policyUrl);
                if ($response['status'] === 200 && $response['error'] === null) {
                    $isPolicyFileReachable = true;
                    $policyFileContent = $response['body'];
                }
            } catch (\Throwable) {
                $isPolicyFileReachable = false;
            }
        }

        $mtaStsCheck = MtaStsCheck::evaluate(
            $domain,
            $mtaStsTxt,
            isPolicyFileReachable: $isPolicyFileReachable,
            policyFileContent: $policyFileContent,
        );

        // 4. TLS-RPT check
        $tlsRptTxt = $this->dnsResolver->getTxtRecords('_smtp._tls.' . $domain);
        $tlsRptCheck = TlsRptCheck::fromTxtRecords($domain, $tlsRptTxt);

        // 5. SPF check
        $spfTxt = $this->dnsResolver->getTxtRecords($domain);
        $spfCheck = SpfCheck::fromTxtRecords($domain, $spfTxt);

        // 6. MX check
        $mxRecords = $this->dnsResolver->getMxRecords($domain);
        $mxCheck = MxCheck::fromMxRecords($domain, $mxRecords);

        return new DomainSecurityReport(
            domain: $domain,
            inspectedAt: gmdate('Y-m-d H:i:s \U\T\C'),
            dmarc: $dmarcCheck,
            bimi: $bimiCheck,
            mtaSts: $mtaStsCheck,
            tlsRpt: $tlsRptCheck,
            spf: $spfCheck,
            mx: $mxCheck,
        );
    }

    public function normalizeDomain(string $input): string
    {
        $domain = trim($input);
        if ($domain === '') {
            throw new \InvalidArgumentException('Please enter a domain name.');
        }

        // Strip scheme if present
        $domain = (string) preg_replace('#^https?://#i', '', $domain);

        // Strip path, port, query or fragment
        $parts = explode('/', $domain, 2);
        $domain = $parts[0];
        $portParts = explode(':', $domain, 2);
        $domain = $portParts[0];

        $domain = strtolower(rtrim($domain, '.'));

        if (!str_contains($domain, '.') || str_ends_with($domain, '.localhost') || $domain === 'localhost') {
            throw new \InvalidArgumentException('Please enter a valid public domain name (e.g. example.com).');
        }

        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain)) {
            throw new \InvalidArgumentException('The domain format is invalid.');
        }

        return $domain;
    }
}
