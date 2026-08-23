<?php

declare(strict_types=1);

namespace App\Audit\Application;

use App\Audit\Domain\AuditRuleEngine;
use App\Audit\Domain\EditorialAdvisoryCatalog;
use App\Audit\Infrastructure\AuditLogger;
use App\Crawl\Application\PageAnalyzer;
use App\Crawl\Application\SitemapInspector;
use App\Crawl\Domain\RobotsPolicy;
use App\Shared\Infrastructure\Http\SafeHttpFetcher;
use App\Shared\Infrastructure\Http\UrlGuard;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class SiteAuditor
{
    public function __construct(
        private UrlGuard $urlGuard,
        private SafeHttpFetcher $fetcher,
        private PageAnalyzer $pageAnalyzer,
        private SitemapInspector $sitemapInspector,
        private AuditRuleEngine $ruleEngine,
        private EditorialAdvisoryCatalog $editorialAdvisories,
        private RobotsPolicy $robotsPolicy,
        private AiSummaryService $aiSummary,
        private AuditLogger $auditLogger,
        private CacheInterface $auditCache,
        private int $maxPages,
        private int $concurrency,
        private int $batchDelayMs,
        private int $cacheTtl,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function audit(string $input, bool $refresh = false): array
    {
        $auditId = $this->auditLogger->newAuditId();
        $requestStarted = hrtime(true);

        try {
            $target = $this->urlGuard->normalize($input);
            $this->auditLogger->log('audit_requested', [
                'audit_id' => $auditId,
                'target' => $this->auditLogger->safeUrl($target),
                'refresh' => $refresh,
                'max_pages' => $this->maxPages,
                'concurrency' => $this->concurrency,
            ]);

            $cacheKey = hash('sha256', 'v3|' . $target . '|' . $this->maxPages . '|' . $this->concurrency);
            $aiCacheKey = hash('sha256', 'ai-v3|' . $cacheKey . '|' . $this->aiSummary->cacheVariant());
            if ($refresh) {
                $this->auditCache->delete($cacheKey);
                $this->auditCache->delete($aiCacheKey);
            }

            $computed = false;
            $report = $this->auditCache->get($cacheKey, function (ItemInterface $item) use ($target, $auditId, &$computed): array {
                $computed = true;
                $item->expiresAfter($this->cacheTtl);
                return $this->performAudit($target, $auditId);
            });
            unset($report['ai_summary']); // Removes the field from reports cached by older builds.
            $report['ai_summary'] = $this->auditCache->get($aiCacheKey, function (ItemInterface $item) use ($report): ?array {
                $item->expiresAfter($this->cacheTtl);
                return $this->aiSummary->summarize($report);
            });
            $report['cache'] = [
                'hit' => !$computed,
                'ttl_seconds' => $this->cacheTtl,
            ];
            $report['editorial_advisories'] = $this->editorialAdvisories->all();

            $this->auditLogger->log('audit_completed', [
                'audit_id' => $auditId,
                'target' => $this->auditLogger->safeUrl($target),
                'cache_hit' => !$computed,
                'request_duration_ms' => $this->elapsedMilliseconds($requestStarted),
                'score' => $report['score'],
                'counts' => $report['counts'],
                'pages_crawled' => $report['summary']['pages_crawled'],
                'urls_discovered' => $report['summary']['urls_discovered'],
                'sitemap_urls' => $report['summary']['sitemap_urls'],
                'ai_summary' => $report['ai_summary'] !== null,
            ]);

            return $report;
        } catch (\Throwable $exception) {
            $this->auditLogger->log('audit_failed', [
                'audit_id' => $auditId,
                'request_duration_ms' => $this->elapsedMilliseconds($requestStarted),
                'error_type' => $exception::class,
                'error' => $this->auditLogger->safeError($exception->getMessage()),
            ]);
            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function performAudit(string $target, string $auditId): array
    {
        $started = hrtime(true);
        $initial = $this->fetcher->fetch($target);
        if ($initial['status'] === 0) {
            throw new \RuntimeException('The website could not be reached: ' . $initial['error']);
        }

        $origin = $this->origin($initial['final_url']);
        $redirectMatrix = array_map(
            fn (array $fetch) => $this->summarizeFetch($fetch),
            array_values($this->fetcher->fetchMany($this->redirectVariants($initial['final_url']))),
        );

        $robotsFetch = $this->fetcher->fetch($origin . '/robots.txt');
        $robots = [
            'url' => $origin . '/robots.txt',
            'status' => $robotsFetch['status'],
            'body' => $robotsFetch['body'],
            'sitemaps' => $this->robotsSitemaps($robotsFetch['body']),
        ];
        $policy = $this->robotsPolicy->parse($robotsFetch['body']);
        $robots['crawl_delay_ms'] = $policy['crawl_delay_ms'];
        $sitemap = $this->sitemapInspector->inspect($origin, $robotsFetch['body']);
        $this->auditLogger->log('sitemap_inspected', [
            'audit_id' => $auditId,
            'origin' => $this->auditLogger->safeUrl($origin),
            'robots_status' => $robotsFetch['status'],
            'sitemap_urls' => $sitemap['url_count'],
            'sitemap_errors' => count($sitemap['errors']),
        ]);

        $crawl = $this->crawl($origin, [$initial['final_url'], $origin . '/'], $policy, $auditId);
        $sitemap['sample_checks'] = $this->checkSitemapSample($sitemap['urls']);
        $trapProbes = $this->probeCrawlerTraps($origin, $crawl['pages']);

        $issues = $this->ruleEngine->evaluate(array_values($crawl['pages']), $redirectMatrix, $robots, $sitemap);
        foreach ($trapProbes as $probe) {
            if ($probe['trap']) {
                $isArbitraryQueryProbe = str_contains($probe['url'], 'seo_audit_probe=1');
                $issues[] = [
                    'severity' => 'critical',
                    'code' => 'crawler-trap-probe',
                    'title' => 'Unbounded URL variant returns indexable content',
                    'detail' => $isArbitraryQueryProbe
                        ? 'The site returned HTTP 200 for a synthetic unknown query string with no consolidating canonical or noindex signal. This indicates arbitrary parameters can create crawlable URL variants.' // phpcs:ignore Generic.Files.LineLength
                        : $probe['url'] . ' returned HTTP 200 with no consolidating canonical or noindex signal.',
                    'evidence' => $probe,
                ];
            }
        }

        $counts = ['critical' => 0, 'warning' => 0, 'info' => 0];
        $countedIssueTypes = [];
        foreach ($issues as $issue) {
            $issueType = $issue['severity'] . '|' . $issue['code'];
            if (isset($countedIssueTypes[$issueType])) {
                continue;
            }
            $countedIssueTypes[$issueType] = true;
            ++$counts[$issue['severity']];
        }
        $score = max(0, 100 - min(70, $counts['critical'] * 12) - min(25, $counts['warning'] * 4) - min(10, $counts['info']));
        $this->auditLogger->log('audit_scored', [
            'audit_id' => $auditId,
            'score' => $score,
            'counts' => $counts,
            'trap_probes' => count($trapProbes),
        ]);

        return [
            'target' => $target,
            'origin' => $origin,
            'generated_at' => gmdate(DATE_ATOM),
            'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'score' => $score,
            'counts' => $counts,
            'summary' => [
                'pages_crawled' => count($crawl['pages']),
                'urls_discovered' => $crawl['discovered'],
                'crawl_truncated' => $crawl['truncated'],
                'sitemap_urls' => $sitemap['url_count'],
                'redirect_variants_tested' => count($redirectMatrix),
            ],
            'issues' => $issues,
            'redirect_matrix' => $redirectMatrix,
            'robots' => $robots,
            'sitemap' => $sitemap,
            'trap_probes' => $trapProbes,
            'pages' => array_map(
                static fn (array $page) => array_diff_key($page, ['links' => true, 'get_forms' => true]),
                array_values($crawl['pages']),
            ),
        ];
    }

    /**
     * @param list<string> $startUrls
     * @param array{rules: list<array{path: string, allow: bool}>, crawl_delay_ms: int} $robotsPolicy
     * @return array{pages: array<string, array<string, mixed>>, discovered: int, truncated: bool}
     */
    private function crawl(string $origin, array $startUrls, array $robotsPolicy, string $auditId): array
    {
        $queue = array_values(array_filter(array_unique($startUrls), fn (string $url) => $this->robotsPolicy->allows($url, $robotsPolicy)));
        $queued = array_fill_keys(array_map($this->crawlKey(...), $queue), true);
        $pages = [];
        $parameterPages = 0;

        while ($queue !== [] && count($pages) < $this->maxPages) {
            $batch = array_splice($queue, 0, min($this->concurrency, $this->maxPages - count($pages)));
            foreach ($this->fetcher->fetchMany($batch) as $requestedUrl => $fetch) {
                $page = $this->pageAnalyzer->analyze($fetch);
                $pages[$requestedUrl] = $page;
                $this->auditLogger->log('page_fetched', [
                    'audit_id' => $auditId,
                    'requested_url' => $this->auditLogger->safeUrl($fetch['requested_url']),
                    'final_url' => $this->auditLogger->safeUrl($fetch['final_url']),
                    'status' => $fetch['status'],
                    'duration_ms' => $fetch['duration_ms'],
                    'redirects' => count($fetch['redirects']),
                    'error' => $this->auditLogger->safeError($fetch['error']),
                ]);

                foreach ($page['links'] as $link) {
                    if (!$this->isCrawlableInternal($origin, $link)) {
                        continue;
                    }
                    if (!$this->robotsPolicy->allows($link, $robotsPolicy)) {
                        continue;
                    }
                    $key = $this->crawlKey($link);
                    if (isset($queued[$key])) {
                        continue;
                    }
                    if (parse_url($link, PHP_URL_QUERY) !== null) {
                        if ($parameterPages >= 10) {
                            continue;
                        }
                        ++$parameterPages;
                    }
                    $queued[$key] = true;
                    $queue[] = $link;
                }
            }
            if ($queue !== [] && count($pages) < $this->maxPages) {
                $this->pause(max($this->batchDelayMs, $robotsPolicy['crawl_delay_ms']));
            }
        }

        return [
            'pages' => $pages,
            'discovered' => count($queued),
            'truncated' => $queue !== [],
        ];
    }

    private function elapsedMilliseconds(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }

    /**
     * @return list<string>
     */
    private function redirectVariants(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $alternateHost = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.' . $host;
        $path = $parts['path'] ?? '/';
        $path = $path === '' ? '/' : $path;
        $slashVariant = $path === '/' ? $path : (str_ends_with($path, '/') ? rtrim($path, '/') : $path . '/');
        $variants = [];
        foreach (['http', 'https'] as $scheme) {
            foreach ([$host, $alternateHost] as $variantHost) {
                $variants[] = $scheme . '://' . $variantHost . $path;
            }
        }
        $variants[] = ($parts['scheme'] ?? 'https') . '://' . $host . $slashVariant;

        return array_values(array_unique($variants));
    }

    /**
     * @param list<string> $urls
     * @return list<array{url: string, status: int, final_url: string, redirects: int, error: ?string}>
     */
    private function checkSitemapSample(array $urls): array
    {
        if ($urls === []) {
            return [];
        }
        $sample = count($urls) <= 10 ? $urls : array_values(array_unique(array_merge(
            array_slice($urls, 0, 5),
            array_slice($urls, -5),
        )));
        $checks = [];
        foreach (array_chunk($sample, max(1, $this->concurrency)) as $index => $chunk) {
            foreach ($this->fetcher->fetchMany($chunk) as $fetch) {
                $checks[] = [
                    'url' => $fetch['requested_url'],
                    'status' => $fetch['status'],
                    'final_url' => $fetch['final_url'],
                    'redirects' => count($fetch['redirects']),
                    'error' => $fetch['error'],
                ];
            }
            if ($index < (int) ceil(count($sample) / max(1, $this->concurrency)) - 1) {
                $this->pause($this->batchDelayMs);
            }
        }

        return $checks;
    }

    /**
     * @param array<string, array<string, mixed>> $pages
     * @return list<array{url: string, final_url: string, status: int, canonical: ?string, robots: ?string, trap: bool}>
     */
    private function probeCrawlerTraps(string $origin, array $pages): array
    {
        $probes = [$origin . '/?seo_audit_probe=1'];
        foreach ($pages as $page) {
            /** @var list<string> $links */
            $links = $page['links'] ?? [];
            foreach ($links as $link) {
                parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
                if (array_key_exists('page', $query)) {
                    $parts = parse_url($link);
                    $probes[] = $origin . ($parts['path'] ?? '/') . '?page=999999999';
                }
                if (count($probes) >= 4) {
                    break 2;
                }
            }
        }

        $results = [];
        foreach ($this->fetcher->fetchMany(array_values(array_unique($probes))) as $fetch) {
            $page = $this->pageAnalyzer->analyze($fetch);
            $robots = strtolower($page['robots'] ?? '');
            $requestedUrl = $fetch['requested_url'];
            $redirectConsolidates = $this->crawlKey($requestedUrl) !== $this->crawlKey($page['final_url']);
            $canonicalConsolidates = $page['canonical'] !== null
                && $this->crawlKey($page['canonical']) !== $this->crawlKey($requestedUrl);
            $results[] = [
                'url' => $requestedUrl,
                'final_url' => $page['final_url'],
                'status' => $page['status'],
                'canonical' => $page['canonical'],
                'robots' => $page['robots'],
                'trap' => $page['status'] === 200
                    && !$redirectConsolidates
                    && !$canonicalConsolidates
                    && !str_contains($robots, 'noindex'),
            ];
        }
        return $results;
    }

    private function isCrawlableInternal(string $originUrl, string $url): bool
    {
        $originHost = strtolower((string) preg_replace('/^www\./', '', (string) parse_url($originUrl, PHP_URL_HOST)));
        $host = strtolower((string) preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)));
        if ($originHost !== $host || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return false;
        }
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        return !preg_match('/\.(?:avif|css|csv|docx?|gif|ico|jpe?g|js|json|mp3|mp4|pdf|png|svg|webm|webp|woff2?|xlsx?|xml|zip)$/', $path);
    }

    private function crawlKey(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return strtolower($url);
        }
        $path = $parts['path'] ?? '/';
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        ksort($query);
        return strtolower(($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '') . $path . ($query === [] ? '' : '?' . http_build_query($query)));
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);
        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * @return list<string>
     */
    private function robotsSitemaps(string $body): array
    {
        preg_match_all('/^\s*Sitemap:\s*(\S+)/im', $body, $matches);
        return array_values(array_unique($matches[1]));
    }

    /**
     * @param array<string, mixed> $fetch
     * @return array{
     *     requested_url: string,
     *     final_url: string,
     *     status: int,
     *     duration_ms: int,
     *     redirects: list<array{url: string, status: int, location: ?string}>,
     *     error: ?string
     * }
     */
    private function summarizeFetch(array $fetch): array
    {
        /** @var list<array{url: string, status: int, location: ?string}> $redirects */
        $redirects = $fetch['redirects'] ?? [];

        return [
            'requested_url' => (string) ($fetch['requested_url'] ?? ''),
            'final_url' => (string) ($fetch['final_url'] ?? ''),
            'status' => (int) ($fetch['status'] ?? 0),
            'duration_ms' => (int) ($fetch['duration_ms'] ?? 0),
            'redirects' => $redirects,
            'error' => isset($fetch['error']) && is_string($fetch['error']) ? $fetch['error'] : null,
        ];
    }

    private function pause(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep(min(10_000, $milliseconds) * 1000);
        }
    }
}
