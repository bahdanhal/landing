<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Geo\Application\GeoAnalyzer;
use PHPUnit\Framework\TestCase;

final class GeoAnalyzerSignalsTest extends TestCase
{
    private GeoAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = (new \ReflectionClass(GeoAnalyzer::class))->newInstanceWithoutConstructor();
    }

    public function testReadsExplicitAiCrawlerPolicyWithoutPenalizingGenericRules(): void
    {
        $robots = "User-agent: *\nAllow: /\n\nUser-agent: GPTBot\nDisallow: /\n";

        self::assertSame('blocked', $this->invoke('botPolicy', $robots, 'GPTBot'));
        self::assertSame('not-addressed', $this->invoke('botPolicy', $robots, 'ClaudeBot'));
    }

    public function testExtractsSchemaTypesAndProvenance(): void
    {
        $jsonLd = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'author' => ['@type' => 'Person', 'name' => 'A'],
            'publisher' => ['@type' => 'Organization', 'name' => 'P'],
            'dateModified' => '2026-08-21',
        ], JSON_UNESCAPED_SLASHES);

        $document = new \DOMDocument();
        $document->loadHTML(sprintf('<!doctype html><html><head><script type="application/ld+json">%s</script></head><body></body></html>', $jsonLd));
        $schema = $this->invoke('schema', new \DOMXPath($document));

        self::assertSame(['Article', 'Organization', 'Person'], $schema['types']);
        self::assertTrue($schema['has_author']);
        self::assertTrue($schema['has_publisher']);
        self::assertTrue($schema['has_date']);
        self::assertSame(1, $schema['valid_count']);
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        return new \ReflectionMethod(GeoAnalyzer::class, $method)->invoke($this->analyzer, ...$arguments);
    }
}
