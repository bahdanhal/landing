<?php

namespace App\Market\Infrastructure;

use App\Market\Application\MarketResearcher;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class AnthropicMarketResearcher implements MarketResearcher
{
    public function __construct(private HttpClientInterface $httpClient, private string $apiKey, private string $model)
    {
    }

    public function observe(Product $product, \DateTimeImmutable $at): PriceObservation
    {
        if (trim($this->apiKey) === '' || trim($this->model) === '') {
            throw new \RuntimeException('Market research AI is not configured.');
        }

        $body = [
            'model' => $this->model,
            'max_tokens' => 1800,
            'temperature' => 0,
            'tools' => [[
                'type' => 'web_search_20250305',
                'name' => 'web_search',
                'max_uses' => 8,
                'user_location' => ['type' => 'approximate', 'country' => 'PL', 'timezone' => 'Europe/Warsaw'],
            ]],
            'system' => 'You conduct a conservative, repeatable AI estimate of second-hand asking prices in Poland. Use live web search to understand the current market, but do not reproduce, cite, name, quote, or return marketplace listings, domains, sellers, or URLs. Compare only the exact product definition. Exclude damaged, parts-only, new, refurbished-as-new, locked, bundled, obvious outliers and non-Polish offers. Return only one strict JSON object with keys median_pln (integer), low_pln (integer), high_pln (integer), sample_size (integer count of comparable offers observed), confidence (low|medium|high), summary (max 450 characters), methodology (max 450 characters). Prices are an AI-assisted estimate of asking prices, not scraped data or completed-sale data. If fewer than three comparable offers can be observed, explain the failure instead of fabricating JSON.',
            'messages' => [[
                'role' => 'user',
                'content' => sprintf("Observation date: %s\nMarket: Poland, prices in PLN\nProduct: %s\nExact comparison definition: %s", $at->format('Y-m-d'), $product->name, $product->definition),
            ]],
        ];
        $payload = [];
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
                'headers' => ['x-api-key' => $this->apiKey, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json'],
                'json' => $body,
                'timeout' => 45,
                'max_duration' => 60,
            ]);
            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Market research API returned HTTP '.$response->getStatusCode().'.');
            }
            $payload = $response->toArray(false);
            if (($payload['stop_reason'] ?? null) !== 'pause_turn') {
                break;
            }
            $body['messages'][] = ['role' => 'assistant', 'content' => $payload['content'] ?? []];
        }
        if (($payload['stop_reason'] ?? null) === 'pause_turn') {
            throw new \RuntimeException('Market research remained paused after three continuations.');
        }
        $text = '';
        foreach ($payload['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }
        $data = $this->decodeJson($text);

        return new PriceObservation(
            $product->slug,
            $at,
            (int) ($data['median_pln'] ?? 0) * 100,
            (int) ($data['low_pln'] ?? 0) * 100,
            (int) ($data['high_pln'] ?? 0) * 100,
            (int) ($data['sample_size'] ?? 0),
            (string) ($data['confidence'] ?? ''),
            substr(trim((string) ($data['summary'] ?? '')), 0, 450),
            substr(trim((string) ($data['methodology'] ?? '')), 0, 450),
        );
    }

    private function decodeJson(string $text): array
    {
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)) ?? '');
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            throw new \RuntimeException('Market research did not return structured evidence.');
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('Market research returned invalid evidence.');
        }

        return $data;
    }
}
