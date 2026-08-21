<?php

namespace App\Tests\Service;

use App\Service\GeoAnalyzer;
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
        $document = new \DOMDocument();
        $document->loadHTML(<<<'HTML'
<!doctype html><html><head><script type="application/ld+json">
{"@context":"https://schema.org","@type":"Article","author":{"@type":"Person","name":"A"},"publisher":{"@type":"Organization","name":"P"},"dateModified":"2026-08-21"}
</script></head><body></body></html>
HTML);
        $schema = $this->invoke('schema', new \DOMXPath($document));

        self::assertSame(['Article', 'Organization', 'Person'], $schema['types']);
        self::assertTrue($schema['has_author']);
        self::assertTrue($schema['has_publisher']);
        self::assertTrue($schema['has_date']);
        self::assertSame(1, $schema['valid_count']);
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        return (new \ReflectionMethod(GeoAnalyzer::class, $method))->invoke($this->analyzer, ...$arguments);
    }
}
