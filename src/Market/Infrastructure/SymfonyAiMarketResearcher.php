<?php

declare(strict_types=1);

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
        return $this->observeMany([$product], $at)[0];
    }

    public function observeMany(array $products, \DateTimeImmutable $at): array
    {
        if ($products === []) {
            return [];
        }
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Warsaw'));
        $historicalRules = $at < $today
            // phpcs:ignore Generic.Files.LineLength
            ? 'This is a retrospective estimate for the requested date. Prefer dated profile information from around that date. Do not silently substitute current prices. Return a conservative estimate only when the period can be reasonably reconstructed; otherwise omit that product.'
            : 'This is a current observation. Use current profile market information.';
        $definitions = array_map(
            static fn (Product $product): array => ['slug' => $product->slug, 'name' => $product->name, 'definition' => $product->definition],
            $products,
        );
        $text = $this->ai->complete(
            // phpcs:ignore Generic.Files.LineLength
            'You conduct conservative, repeatable estimates of realistic consumer second-hand asking prices in Poland (reflecting actual private individual listings on Polish classifieds like OLX and Allegro Lokalnie, not inflated commercial/dealer/refurbished store prices). Research all supplied products together to minimize cost. Use provider-hosted web search, but never reproduce, cite, name, quote, or return any marketplace, domain, seller, listing, or URL. Apply each exact product definition and exclude damaged, parts-only, new, refurbished-as-new, locked, bundled, obvious outliers and non-Polish offers. Return only one strict JSON object with an observations array. Each item must contain slug, median_pln, low_pln, high_pln, sample_size and confidence (low|medium|high). Prices are AI-assisted asking-price estimates, not scraped or completed-sale data. Omit a product rather than inventing figures when fewer than three comparables can be reasonably assessed.',
            // phpcs:ignore Generic.Files.LineLength
            sprintf("Observation date: %s\n%s\nMarket: Poland, prices in PLN\nProducts: %s", $at->format('Y-m-d'), $historicalRules, json_encode($definitions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            AiUseCase::MarketResearch,
        );
        $data = $this->decodeJson($text);
        $rows = $data['observations'] ?? (isset($data['median_pln']) ? [$data + ['slug' => $products[0]->slug]] : []);
        if (!is_array($rows)) {
            throw new \RuntimeException('Market research returned an invalid observations list.');
        }
        $allowed = array_fill_keys(array_map(static fn (Product $product): string => $product->slug, $products), true);
        $observations = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($allowed[(string) ($row['slug'] ?? '')])) {
                continue;
            }
            try {
                $methodology = $at < $today
                    ? PriceObservation::METHODOLOGY_RETROSPECTIVE
                    : PriceObservation::METHODOLOGY_CURRENT;

                $observations[] = new PriceObservation(
                    (string) $row['slug'],
                    $at,
                    (int) round(((float) ($row['median_pln'] ?? 0)) * 100),
                    (int) round(((float) ($row['low_pln'] ?? 0)) * 100),
                    (int) round(((float) ($row['high_pln'] ?? 0)) * 100),
                    (int) ($row['sample_size'] ?? 0),
                    (string) ($row['confidence'] ?? ''),
                    '',
                    $methodology,
                );
            } catch (\InvalidArgumentException) {
                continue;
            }
        }
        if ($observations === []) {
            throw new \RuntimeException('Market research returned no usable observations.');
        }

        return $observations;
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
