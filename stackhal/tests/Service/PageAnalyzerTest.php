<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Crawl\Infrastructure\HttpFetcher;
use App\Crawl\Application\PageAnalyzer;
use App\Crawl\Infrastructure\UrlGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class PageAnalyzerTest extends TestCase
{
    public function testExtractsIndexingSignalsAndResolvedLinks(): void
    {
        $fetcher = new HttpFetcher(new MockHttpClient(), new UrlGuard(), 5, 1_000_000);
        $analyzer = new PageAnalyzer($fetcher);
        $html = <<<'HTML'
<!doctype html><html lang="en"><head>
<title>Collector guide</title>
<meta name="description" content="A useful guide">
<link rel="canonical" href="/guides/collector">
</head><body><h1>Collector guide</h1><p>Useful original content.</p>
<a href="../cars?page=2#inventory">More cars</a>
<form action="/search"><input name="q"><select name="make"></select></form>
</body></html>
HTML;

        $page = $analyzer->analyze([
            'requested_url' => 'https://example.com/guides/collector',
            'final_url' => 'https://example.com/guides/collector',
            'status' => 200,
            'headers' => [],
            'duration_ms' => 12,
            'redirects' => [],
            'content_type' => 'text/html; charset=UTF-8',
            'body' => $html,
            'error' => null,
        ]);

        self::assertSame('Collector guide', $page['title']);
        self::assertSame('https://example.com/guides/collector', $page['canonical']);
        self::assertSame(['https://example.com/cars?page=2'], $page['links']);
        self::assertSame(['q', 'make'], $page['get_forms'][0]['parameters']);
        self::assertSame(['Collector guide'], $page['h1']);
    }
}
