<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Audit\Domain\AuditRuleEngine;
use PHPUnit\Framework\TestCase;

final class AuditRuleEngineTest extends TestCase
{
    public function testFlagsCanonicalParameterAndBrokenSitemapUrls(): void
    {
        $page = [
            'final_url' => 'https://example.com/cars?sort=price',
            'status' => 200,
            'content_type' => 'text/html',
            'error' => null,
            'canonical_count' => 0,
            'canonical' => null,
            'title' => 'Cars',
            'h1' => ['Cars'],
            'description' => 'Inventory',
            'word_count' => 100,
            'content_hash' => 'abc',
            'get_forms' => [],
        ];
        $redirect = [[
            'status' => 200,
            'final_url' => 'https://example.com/',
            'requested_url' => 'https://example.com/',
            'redirects' => [],
        ]];
        $sitemap = [
            'errors' => [],
            'urls' => ['https://example.com/cars?sort=price'],
            'sample_checks' => [[
                'url' => 'https://example.com/cars?sort=price',
                'status' => 404,
                'final_url' => 'https://example.com/cars?sort=price',
                'redirects' => 0,
                'error' => null,
            ]],
        ];

        $issues = (new AuditRuleEngine())->evaluate([$page], $redirect, [
            'status' => 200,
            'url' => 'https://example.com/robots.txt',
        ], $sitemap);
        $codes = array_column($issues, 'code');

        self::assertContains('missing-canonical', $codes);
        self::assertContains('parameter-space', $codes);
        self::assertContains('sitemap-parameters', $codes);
        self::assertContains('sitemap-broken-url', $codes);
    }

    public function testSafeParameterAndNoindexPagesDoNotCreateWarnings(): void
    {
        $pages = [[
            'url' => 'https://example.com/cars?sort=price',
            'final_url' => 'https://example.com/cars?sort=price',
            'status' => 200,
            'content_type' => 'text/html',
            'error' => null,
            'canonical_count' => 1,
            'canonical' => 'https://example.com/cars',
            'title' => 'Cars',
            'h1' => ['Cars'],
            'description' => 'Inventory',
            'robots' => 'noindex,follow',
            'word_count' => 100,
            'content_hash' => 'safe-parameter-page',
            'get_forms' => [['action' => 'https://example.com/cars', 'parameters' => ['sort']]],
        ]];
        $redirect = [[
            'status' => 200,
            'final_url' => 'https://example.com/',
            'requested_url' => 'https://example.com/',
            'redirects' => [],
        ]];
        $sitemap = ['errors' => [], 'urls' => ['https://example.com/cars'], 'sample_checks' => []];

        $issues = (new AuditRuleEngine())->evaluate($pages, $redirect, [
            'status' => 200,
            'url' => 'https://example.com/robots.txt',
        ], $sitemap);
        $codes = array_column($issues, 'code');

        self::assertNotContains('parameter-space', $codes);
        self::assertContains('parameter-entry-points', $codes);
        self::assertNotContains('sitemap-coverage', $codes);
    }
}
