<?php

declare(strict_types=1);

namespace App\Crawl\Application;

use App\Crawl\Infrastructure\HttpFetcher;

final readonly class SitemapInspector
{
    private const MAX_SITEMAPS = 10;
    private const MAX_URLS = 10_000;

    public function __construct(private HttpFetcher $fetcher)
    {
    }

    public function inspect(string $origin, string $robotsBody): array
    {
        preg_match_all('/^\s*Sitemap:\s*(\S+)/im', $robotsBody, $matches);
        $candidates = array_values(array_unique($matches[1] ?? []));
        if ($candidates === []) {
            $candidates = [rtrim($origin, '/') . '/sitemap.xml'];
        }

        $queue = $candidates;
        $seen = [];
        $urls = [];
        $files = [];
        $errors = [];

        while ($queue !== [] && count($seen) < self::MAX_SITEMAPS && count($urls) < self::MAX_URLS) {
            $sitemapUrl = array_shift($queue);
            if (isset($seen[$sitemapUrl])) {
                continue;
            }
            $seen[$sitemapUrl] = true;
            $fetch = $this->fetcher->fetch($sitemapUrl);
            $files[] = [
                'url' => $sitemapUrl,
                'status' => $fetch['status'],
                'final_url' => $fetch['final_url'],
                'redirects' => count($fetch['redirects']),
            ];
            if ($fetch['status'] !== 200 || $fetch['body'] === '') {
                $errors[] = sprintf('%s returned HTTP %s.', $sitemapUrl, $fetch['status'] ?: 'error');
                continue;
            }

            $document = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $loaded = $document->loadXML($fetch['body'], LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if (!$loaded) {
                $errors[] = $sitemapUrl . ' is not valid XML.';
                continue;
            }

            $rootName = strtolower($document->documentElement?->localName ?? '');
            foreach ($document->getElementsByTagName('loc') as $loc) {
                $value = trim($loc->textContent);
                if ($value === '') {
                    continue;
                }
                if ($rootName === 'sitemapindex') {
                    $queue[] = $value;
                } else {
                    $urls[$value] = true;
                    if (count($urls) >= self::MAX_URLS) {
                        break;
                    }
                }
            }
        }

        return [
            'files' => $files,
            'urls' => array_keys($urls),
            'url_count' => count($urls),
            'errors' => $errors,
            'truncated' => count($urls) >= self::MAX_URLS || count($seen) >= self::MAX_SITEMAPS,
        ];
    }
}
