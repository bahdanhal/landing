<?php

namespace App\Tests\Service;

use App\Service\AiSummaryService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AiSummaryServiceTest extends TestCase
{
    public function testIsSilentWhenNotConfigured(): void
    {
        $service = new AiSummaryService(new MockHttpClient(), '', '');

        self::assertNull($service->summarize([]));
    }

    public function testParsesAValidStructuredSummary(): void
    {
        $response = new MockResponse(json_encode(['content' => [[
            'type' => 'text',
            'text' => '{"overview":"Fix canonicalization first.","priorities":[{"title":"Canonicals","why":"Duplicates split signals.","action":"Add self-referencing canonicals."}]}',
        ]]], JSON_THROW_ON_ERROR), ['http_code' => 200]);
        $service = new AiSummaryService(new MockHttpClient($response), 'test-key', 'test-model');
        $summary = $service->summarize([
            'target' => 'https://example.com/',
            'score' => 50,
            'counts' => ['critical' => 1, 'warning' => 0, 'info' => 0],
            'summary' => [],
            'issues' => [],
        ]);

        self::assertSame('Fix canonicalization first.', $summary['overview']);
        self::assertSame('Canonicals', $summary['priorities'][0]['title']);
    }
}
