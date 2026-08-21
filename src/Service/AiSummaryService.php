<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiSummaryService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    /** @return array{overview:string,priorities:list<array{title:string,why:string,action:string}>}|null */
    public function summarize(array $report): ?array
    {
        if (trim($this->apiKey) === '' || trim($this->model) === '') {
            return null;
        }

        $evidence = [
            'target' => $report['target'],
            'score' => $report['score'],
            'counts' => $report['counts'],
            'crawl_summary' => $report['summary'],
            'findings' => array_slice(array_map(static fn (array $issue) => [
                'severity' => $issue['severity'],
                'code' => $issue['code'],
                'detail' => $issue['detail'],
            ], $report['issues']), 0, 24),
        ];

        try {
            $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => 700,
                    'temperature' => 0,
                    'system' => 'You are a technical SEO analyst. Use only the supplied deterministic evidence. Return strict JSON with overview (2-3 concise sentences) and priorities (up to 5 objects with title, why, action). Do not use markdown or invent facts. The seo_audit_probe query is a synthetic test proving that arbitrary unknown query strings are accepted; never describe it as a real site feature or recommend blocking that probe name specifically.',
                    'messages' => [[
                        'role' => 'user',
                        'content' => json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    ]],
                ],
                'timeout' => 15,
                'max_duration' => 20,
            ]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $payload = $response->toArray(false);
            $text = '';
            foreach ($payload['content'] ?? [] as $block) {
                if (($block['type'] ?? null) === 'text') {
                    $text .= $block['text'] ?? '';
                }
            }
            $text = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));
            $summary = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($summary) || !is_string($summary['overview'] ?? null) || !is_array($summary['priorities'] ?? null)) {
                return null;
            }
            $priorities = [];
            foreach (array_slice($summary['priorities'], 0, 5) as $priority) {
                if (is_array($priority) && is_string($priority['title'] ?? null) && is_string($priority['why'] ?? null) && is_string($priority['action'] ?? null)) {
                    $priorities[] = [
                        'title' => $priority['title'],
                        'why' => $priority['why'],
                        'action' => $priority['action'],
                    ];
                }
            }

            return ['overview' => $summary['overview'], 'priorities' => $priorities];
        } catch (\Throwable) {
            return null;
        }
    }

    public function cacheVariant(): string
    {
        return hash('sha256', $this->model);
    }
}
