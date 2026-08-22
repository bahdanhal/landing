<?php

namespace App\Mcp;

use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservationRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class MarketPriceTools
{
    public function __construct(private ProductCatalog $catalog, private PriceObservationRepository $observations)
    {
    }

    #[McpTool(name: 'list_polish_used_price_products', description: 'List Apple product configurations tracked by Bahdan’s Toolbox for Polish second-hand asking-price history.')]
    public function listProducts(): string
    {
        return $this->json([
            'products' => array_map(fn ($product) => [
                'slug' => $product->slug,
                'name' => $product->name,
                'category' => $product->category,
                'configuration' => $product->specifications,
                'has_observations' => $this->observations->latest($product->slug) !== null,
                'canonical_url' => $this->canonicalUrl($product->slug),
            ], $this->catalog->all()),
            'terms' => 'Public read-only estimates; no account or API key required.',
        ]);
    }

    #[McpTool(name: 'get_polish_used_price_history', description: 'Get dated AI-assisted Polish used asking-price estimates for one exact product configuration. Returns no marketplace names, listings, sellers, or URLs.')]
    public function getHistory(#[Schema(description: 'Product slug returned by list_polish_used_price_products.')] string $slug): string
    {
        $product = $this->catalog->get($slug);
        if ($product === null) {
            return $this->json(['error' => 'Unknown product slug.', 'suggestion' => 'Call list_polish_used_price_products first.']);
        }

        return $this->json([
            'product' => ['slug' => $product->slug, 'name' => $product->name, 'configuration' => $product->specifications],
            'market' => 'Poland',
            'currency' => 'PLN',
            'observations' => array_map(static fn ($item) => [
                'observed_at' => $item->observedAt->format('Y-m-d'),
                'median_pln' => $item->medianGrosz / 100,
                'low_pln' => $item->lowGrosz / 100,
                'high_pln' => $item->highGrosz / 100,
                'sample_size' => $item->sampleSize,
                'confidence' => $item->confidence,
            ], $this->observations->history($slug)),
            'methodology' => 'AI-assisted estimate of comparable public asking prices; not scraped data, completed-sale statistics, a valuation, or purchasing advice.',
            'canonical_url' => $this->canonicalUrl($slug),
        ]);
    }

    private function canonicalUrl(string $slug): string
    {
        return 'https://bahdan-hal.ovh/tools/poland-used-price-index/'.$slug;
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
