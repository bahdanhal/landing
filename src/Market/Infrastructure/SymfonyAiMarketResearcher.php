<?php

namespace App\Market\Infrastructure;

use App\Market\Application\MarketResearcher;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;
use App\Shared\AI\AiClient;
use App\Shared\AI\AiUseCase;

final readonly class SymfonyAiMarketResearcher implements MarketResearcher
{
    public function __construct(private AiClient $ai)
    {
    }

    public function observe(Product $product, \DateTimeImmutable $at): PriceObservation
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Warsaw'));
        $historicalRules = $at < $today
            ? 'This is a historical observation. Use only public web-archive captures whose capture timestamp is on, or as close as practical to, the requested date. The returned figures must describe evidence visible in those dated captures, not current prices and not a backwards projection. If enough dated archived comparables cannot be verified, explain the failure instead of returning JSON.'
            : 'This is a current observation. Use current public market information.';
        $text = $this->ai->complete(
            'You conduct a conservative, repeatable AI estimate of second-hand asking prices in Poland. Use provider-hosted web search to understand the current market, but never reproduce, cite, name, quote, or return any marketplace, domain, seller, listing, or URL. Compare only the exact product definition. Exclude damaged, parts-only, new, refurbished-as-new, locked, bundled, obvious outliers and non-Polish offers. Return only one strict JSON object with keys median_pln (integer), low_pln (integer), high_pln (integer), sample_size (integer), confidence (low|medium|high). Prices are an AI-assisted estimate of asking prices, not scraped or completed-sale data. If fewer than three comparable offers can be observed, explain the failure instead of fabricating JSON.',
            sprintf("Observation date: %s\n%s\nMarket: Poland, prices in PLN\nProduct: %s\nExact comparison definition: %s", $at->format('Y-m-d'), $historicalRules, $product->name, $product->definition),
            AiUseCase::MarketResearch,
        );
        $data = $this->decodeJson($text);

        return new PriceObservation(
            $product->slug,
            $at,
            (int) ($data['median_pln'] ?? 0) * 100,
            (int) ($data['low_pln'] ?? 0) * 100,
            (int) ($data['high_pln'] ?? 0) * 100,
            (int) ($data['sample_size'] ?? 0),
            (string) ($data['confidence'] ?? ''),
            '',
            'AI-assisted estimate from current public market information; no marketplace identities, listings, or links retained.',
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
