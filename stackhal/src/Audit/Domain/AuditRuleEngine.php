<?php

declare(strict_types=1);

namespace App\Audit\Domain;

final readonly class AuditRuleEngine
{
    /**
     * @param list<array<string, mixed>> $pages
     * @param list<array<string, mixed>> $redirectMatrix
     * @param array<string, mixed> $robots
     * @param array<string, mixed> $sitemap
     * @return list<array{severity: string, code: string, title: string, detail: string, evidence: array<mixed>}>
     */
    public function evaluate(array $pages, array $redirectMatrix, array $robots, array $sitemap): array
    {
        $issues = [];
        $add = static function (string $severity, string $code, string $title, string $detail, array $evidence = []) use (&$issues): void {
            $issues[] = compact('severity', 'code', 'title', 'detail', 'evidence');
        };

        $successfulVariants = array_filter($redirectMatrix, static fn (array $item) => $item['status'] >= 200 && $item['status'] < 400);
        $finalVariants = array_unique(array_map(static fn (array $item) => $item['final_url'], $successfulVariants));
        if (count($finalVariants) > 1) {
            // phpcs:ignore Generic.Files.LineLength
            $add('critical', 'host-canonicalization', 'Protocol or hostname variants do not converge', 'All HTTP/HTTPS and www/non-www variants should reach one preferred URL.', array_values($finalVariants));
        }
        foreach ($redirectMatrix as $variant) {
            if (count($variant['redirects']) > 1) {
                // phpcs:ignore Generic.Files.LineLength
                $add('warning', 'redirect-chain', 'Redirect chain detected', $variant['requested_url'] . ' needs ' . count($variant['redirects']) . ' redirects.', $variant['redirects']);
            }
        }

        $titles = [];
        $hashes = [];
        $unsafeParameterUrls = [];
        $getForms = [];
        foreach ($pages as $page) {
            $url = $page['final_url'];
            $requestedUrl = $page['url'] ?? $url;
            if ($page['status'] === 0) {
                $add('warning', 'fetch-error', 'Page could not be fetched', $url . ': ' . ($page['error'] ?? 'Unknown error'));
                continue;
            }
            if ($page['status'] >= 400) {
                $add('critical', 'broken-internal-url', 'Internal URL returns an error', $url . ' returned HTTP ' . $page['status'] . '.');
                continue;
            }
            if (!str_contains($page['content_type'], 'html')) {
                continue;
            }
            if ($page['canonical_count'] === 0) {
                $add('critical', 'missing-canonical', 'Canonical tag is missing', $url . ' has no rel="canonical".');
            } elseif ($page['canonical_count'] > 1) {
                $add('critical', 'multiple-canonicals', 'Multiple canonical tags found', $url . ' contains ' . $page['canonical_count'] . ' canonical tags.');
            } elseif ($this->normalized($page['canonical']) !== $this->normalized($url)) {
                $add('warning', 'canonical-mismatch', 'Canonical points elsewhere', $url . ' declares ' . $page['canonical'] . '.');
            }
            if ($page['title'] === null) {
                $add('warning', 'missing-title', 'Page title is missing', $url . ' has no title element.');
            } else {
                $titles[$page['title']][] = $url;
            }
            if (count($page['h1']) !== 1) {
                $add('warning', 'h1-count', 'Page should have one clear H1', $url . ' has ' . count($page['h1']) . ' H1 headings.');
            }
            if ($page['description'] === null) {
                $add('info', 'missing-description', 'Meta description is missing', $url . ' has no meta description.');
            }
            if (($page['word_count'] ?? 0) < 80) {
                $add('info', 'thin-page', 'Very little indexable text', $url . ' has about ' . $page['word_count'] . ' words.');
            }
            if ($page['content_hash']) {
                $hashes[$page['content_hash']][] = $url;
            }
            if (parse_url($requestedUrl, PHP_URL_QUERY)) {
                $robotsDirective = strtolower($page['robots'] ?? '');
                $redirectConsolidates = $this->normalized($requestedUrl) !== $this->normalized($url);
                $canonicalConsolidates = $page['canonical'] !== null
                    && $this->normalized($page['canonical']) !== $this->normalized($requestedUrl);
                if (!str_contains($robotsDirective, 'noindex') && !$redirectConsolidates && !$canonicalConsolidates) {
                    $unsafeParameterUrls[] = $requestedUrl;
                }
            }
            array_push($getForms, ...$page['get_forms']);
        }

        foreach ($titles as $title => $urls) {
            if (count($urls) > 1) {
                // phpcs:ignore Generic.Files.LineLength
                $add('warning', 'duplicate-title', 'Duplicate page title', 'The title “' . $title . '” appears on ' . count($urls) . ' crawled pages.', array_slice($urls, 0, 10));
            }
        }
        foreach ($hashes as $urls) {
            if (count($urls) > 1) {
                // phpcs:ignore Generic.Files.LineLength
                $add('critical', 'duplicate-content', 'Duplicate page bodies found', count($urls) . ' URLs contain effectively identical text.', array_slice($urls, 0, 10));
            }
        }
        if ($unsafeParameterUrls !== []) {
            // phpcs:ignore Generic.Files.LineLength
            $add('warning', 'parameter-space', 'Parameter-generated URL space detected', 'GET filters, searches, sorting, or parameter links can multiply crawlable URLs. Canonicalize, noindex, block, or return 404 for invalid combinations.', [
                'sample_urls' => array_slice(array_unique($unsafeParameterUrls), 0, 10),
                'get_forms' => array_slice($getForms, 0, 10),
            ]);
        } elseif ($getForms !== []) {
            // phpcs:ignore Generic.Files.LineLength
            $add('info', 'parameter-entry-points', 'GET forms create parameter entry points', 'GET forms were found, but no sampled parameter URL was shown to be independently indexable. Keep canonical and noindex handling in place.', [
                'get_forms' => array_slice($getForms, 0, 10),
            ]);
        }

        if ($robots['status'] !== 200) {
            // phpcs:ignore Generic.Files.LineLength
            $add('warning', 'robots-missing', 'robots.txt is unavailable', 'Expected HTTP 200 at ' . $robots['url'] . '; received ' . $robots['status'] . '.');
        }
        foreach ($sitemap['errors'] as $error) {
            $add('warning', 'sitemap-error', 'Sitemap problem', $error);
        }
        foreach ($sitemap['sample_checks'] ?? [] as $sample) {
            if ($sample['status'] === 0 || $sample['status'] >= 400) {
                // phpcs:ignore Generic.Files.LineLength
                $add('critical', 'sitemap-broken-url', 'Sitemap URL is not crawlable', $sample['url'] . ' returned ' . ($sample['status'] ?: 'a fetch error') . '.', $sample);
            } elseif ($sample['redirects'] > 0 || $this->normalized($sample['url']) !== $this->normalized($sample['final_url'])) {
                // phpcs:ignore Generic.Files.LineLength
                $add('warning', 'sitemap-redirect', 'Sitemap URL redirects', $sample['url'] . ' should be replaced by its final canonical URL.', $sample);
            }
        }

        $sitemapUrls = $sitemap['urls'];
        $querySitemap = array_values(array_filter($sitemapUrls, static fn (string $url) => parse_url($url, PHP_URL_QUERY) !== null));
        if ($querySitemap !== []) {
            // phpcs:ignore Generic.Files.LineLength
            $add('critical', 'sitemap-parameters', 'Sitemap contains parameter URLs', 'A sitemap should normally contain preferred canonical URLs only.', array_slice($querySitemap, 0, 10));
        }
        $sitemapSet = array_fill_keys(array_map([$this, 'normalized'], $sitemapUrls), true);
        $missing = [];
        foreach ($pages as $page) {
            $robotsDirective = strtolower($page['robots'] ?? '');
            $isHtml = str_contains($page['content_type'] ?? '', 'html');
            $hasQuery = parse_url($page['final_url'], PHP_URL_QUERY) !== null;
            $canonicalElsewhere = $page['canonical'] !== null
                && $this->normalized($page['canonical']) !== $this->normalized($page['final_url']);
            $isIndexable = $page['status'] === 200
                && $isHtml
                && !$hasQuery
                && !$canonicalElsewhere
                && !str_contains($robotsDirective, 'noindex');
            if ($isIndexable && !isset($sitemapSet[$this->normalized($page['final_url'])])) {
                $missing[] = $page['final_url'];
            }
        }
        if ($sitemapUrls !== [] && $missing !== []) {
            // phpcs:ignore Generic.Files.LineLength
            $add('warning', 'sitemap-coverage', 'Crawlable pages are absent from the sitemap', count($missing) . ' crawled 200 pages were not found in the sitemap.', array_slice($missing, 0, 15));
        }

        $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($issues, static fn (array $a, array $b) => [$rank[$a['severity']], $a['code']] <=> [$rank[$b['severity']], $b['code']]);
        return $issues;
    }

    private function normalized(?string $url): string
    {
        if ($url === null) {
            return '';
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return strtolower(rtrim($url, '/'));
        }
        $path = $parts['path'] ?? '/';
        $path = $path === '/' ? '/' : rtrim($path, '/');
        return strtolower(($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '') . $path . (isset($parts['query']) ? '?' . $parts['query'] : ''));
    }
}
