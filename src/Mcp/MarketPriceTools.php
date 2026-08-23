<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Market\Application\GetProductPriceHistory;
use App\Market\Application\ProductCatalog;
use App\Market\Application\RecordPriceObservation;
use App\Market\Domain\PriceObservation;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class MarketPriceTools
{
    public function __construct(
        private ProductCatalog $catalog,
        private GetProductPriceHistory $priceHistory,
        private RecordPriceObservation $recordObservation,
        private ?AdminAccess $adminAccess = null,
    ) {
    }

    #[McpTool(
        name: 'list_polish_used_price_products',
        description: 'List Apple product configurations tracked by Bahdan’s Toolbox for Polish second-hand asking-price history.'
    )]
    public function listProducts(): string
    {
        return $this->json([
            'products' => array_map(fn ($product) => [
                'slug' => $product->slug,
                'name' => $product->name,
                'category' => $product->category,
                'configuration' => $product->specifications,
                'has_observations' => $this->priceHistory->latestForProduct($product->slug) !== null,
                'canonical_url' => $this->canonicalUrl($product->slug),
            ], $this->catalog->all()),
            'terms' => 'Public read-only estimates; no account or API key required.',
        ]);
    }

    #[McpTool(
        name: 'get_polish_used_price_history',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Get dated, manually reviewed Polish used asking-price observations for one exact product configuration.'
    )]
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
            ], $this->priceHistory->forProduct($slug)),
            // phpcs:ignore Generic.Files.LineLength
            'methodology' => 'Manually reviewed aggregate of comparable public asking prices; not scraped data, completed-sale statistics, a valuation, or purchasing advice.',
            'canonical_url' => $this->canonicalUrl($slug),
        ]);
    }

    #[McpTool(
        name: 'update_polish_used_price_observation',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only tool: Add or update a verified Polish marketplace used asking-price observation. Authorization is handled via the Authorization header — do NOT pass any token argument.'
    )]
    public function updateObservation(
        #[Schema(description: 'Product slug to update (must exist in catalog).')] string $slug,
        #[Schema(description: 'Observed fair market median price in PLN.')] float $median_pln,
        #[Schema(description: 'Optional lower bound price in PLN (defaults to median * 0.88).')] ?float $low_pln = null,
        #[Schema(description: 'Optional upper bound price in PLN (defaults to median * 1.14).')] ?float $high_pln = null,
        #[Schema(description: 'Optional sample size count (defaults to 8).')] ?int $sample_size = null,
        #[Schema(description: 'Optional confidence level: low, medium, high (defaults to high).')] ?string $confidence = null,
        #[Schema(description: 'Optional observation date in YYYY-MM-DD or ISO 8601 format (defaults to current date).')] ?string $observed_at = null,
        #[Schema(description: 'Optional summary note or verification details.')] ?string $summary = null,
    ): string {
        if ($this->adminAccess?->isGranted() !== true) {
            return $this->json(['error' => 'Unauthorized: Invalid admin token.']);
        }

        $product = $this->catalog->get($slug);
        if ($product === null) {
            return $this->json(['error' => 'Unknown product slug.', 'suggestion' => 'Call list_polish_used_price_products first.']);
        }

        $medianGrosz = (int) round($median_pln * 100);
        $lowGrosz = $low_pln !== null ? (int) round($low_pln * 100) : (int) round($medianGrosz * 0.88);
        $highGrosz = $high_pln !== null ? (int) round($high_pln * 100) : (int) round($medianGrosz * 1.14);

        if ($medianGrosz <= 0 || $lowGrosz <= 0 || $highGrosz < $medianGrosz || $medianGrosz < $lowGrosz) {
            return $this->json(['error' => 'Inconsistent prices. Ensure low <= median <= high and median > 0.']);
        }

        $sampleSize = $sample_size ?? 8;
        if ($sampleSize < 3) {
            return $this->json(['error' => 'Sample size must be at least 3.']);
        }

        $confidenceLevel = $confidence ?? 'high';
        if (!in_array($confidenceLevel, ['low', 'medium', 'high'], true)) {
            return $this->json(['error' => 'Confidence must be one of: low, medium, high.']);
        }

        try {
            $observedDate = $observed_at !== null ? new \DateTimeImmutable($observed_at) : new \DateTimeImmutable('now');
        } catch (\Throwable) {
            return $this->json(['error' => 'Invalid date format. Use YYYY-MM-DD or ISO 8601.']);
        }

        $note = $summary ?? 'Verified and calibrated against Polish secondary market listings.';

        try {
            $this->recordObservation->execute(
                $slug,
                $observedDate,
                $medianGrosz,
                $lowGrosz,
                $highGrosz,
                $sampleSize,
                $confidenceLevel,
                $note,
                PriceObservation::METHODOLOGY_MANUAL
            );
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()]);
        }

        return $this->json([
            'status' => 'success',
            'message' => 'Observation saved successfully.',
            'product' => [
                'slug' => $product->slug,
                'name' => $product->name,
            ],
            'observation' => [
                'observed_at' => $observedDate->format('Y-m-d H:i:sP'),
                'median_pln' => $medianGrosz / 100,
                'low_pln' => $lowGrosz / 100,
                'high_pln' => $highGrosz / 100,
                'sample_size' => $sampleSize,
                'confidence' => $confidenceLevel,
                'summary' => $note,
            ],
            'canonical_url' => $this->canonicalUrl($slug),
        ]);
    }

    private function canonicalUrl(string $slug): string
    {
        return 'https://bahdanhal.pl/tools/poland-used-price-index/' . $slug;
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
