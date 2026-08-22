<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Analytics\Application\TrafficAnalytics;
use App\Audit\Infrastructure\AuditLogger;
use App\Lead\Domain\Lead;
use App\Lead\Domain\LeadRepository;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Domain\PriceTip;
use App\Market\Infrastructure\JsonPriceTipRepository;
use App\Market\Infrastructure\JsonProductRequestStore;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class AdminTools
{
    public function __construct(
        private AdminAccess $access,
        private LeadRepository $leads,
        private JsonProductRequestStore $productRequests,
        private JsonPriceTipRepository $priceTips,
        private ProductCatalog $catalog,
        private PriceObservationRepository $observations,
        private AuditLogger $auditLogger,
        private TrafficAnalytics $trafficAnalytics,
    ) {
    }

    #[McpTool(
        name: 'get_admin_dashboard_statistics',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: Get privacy-preserving traffic, submission, SEO audit and market-coverage statistics. Requires an Authorization: Bearer header.'
    )]
    public function statistics(): string
    {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }

        $leads = $this->leads->all();
        $requests = $this->productRequests->all();
        $tips = $this->priceTips->all();
        $auditEvents = $this->auditLogger->events();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $sevenDaysAgo = $now->modify('-7 days');
        $thirtyDaysAgo = $now->modify('-30 days');
        $observationCount = 0;
        $productsWithHistory = 0;
        $productsWithoutHistory = [];
        $staleProducts = [];

        foreach ($this->catalog->all() as $product) {
            $history = $this->observations->history($product->slug);
            $observationCount += count($history);
            $latest = $history[0] ?? null;
            if ($latest === null) {
                $productsWithoutHistory[] = $product->slug;
                continue;
            }

            ++$productsWithHistory;
            if ($latest->observedAt < $thirtyDaysAgo) {
                $staleProducts[] = $product->slug;
            }
        }

        return $this->json([
            'generated_at' => $now->format(DATE_ATOM),
            'submissions' => [
                'contact_leads' => $this->submissionStats(
                    $leads,
                    static fn (Lead $lead): \DateTimeImmutable => $lead->createdAt,
                    $sevenDaysAgo,
                    $thirtyDaysAgo,
                ),
                'product_requests' => $this->submissionStats(
                    $requests,
                    static fn (array $request): \DateTimeImmutable => new \DateTimeImmutable($request['timestamp']),
                    $sevenDaysAgo,
                    $thirtyDaysAgo,
                ),
                'active_price_tips' => $this->submissionStats(
                    $tips,
                    static fn (PriceTip $tip): \DateTimeImmutable => $tip->submittedAt,
                    $sevenDaysAgo,
                    $thirtyDaysAgo,
                ),
            ],
            'seo_audits' => $this->auditStatistics($auditEvents, $sevenDaysAgo, $thirtyDaysAgo),
            'traffic' => $this->trafficAnalytics->summary($now),
            'lead_sources' => $this->frequencies(array_map(static fn (Lead $lead): string => $lead->source, $leads)),
            'requested_products' => $this->frequencies(array_map(
                static fn (array $request): string => $request['product'],
                $requests,
            )),
            'price_tip_products' => $this->frequencies(array_map(
                static fn (PriceTip $tip): string => $tip->productSlug,
                $tips,
            )),
            'market_coverage' => [
                'tracked_products' => count($this->catalog->all()),
                'products_with_history' => $productsWithHistory,
                'observation_points' => $observationCount,
                'products_without_history' => $productsWithoutHistory,
                'products_not_reviewed_in_30_days' => $staleProducts,
            ],
        ]);
    }

    #[McpTool(
        name: 'list_admin_recent_audits',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: List recent SEO audit runs with sanitized targets, status, score and runtime details. Requires an Authorization: Bearer header.'
    )]
    public function recentAudits(
        #[Schema(description: 'Maximum audit runs to return, from 1 to 100.')] int $limit = 50,
    ): string {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }
        if (!$this->validLimit($limit)) {
            return $this->invalidLimit();
        }

        $runs = [];
        foreach (array_reverse($this->auditLogger->events()) as $event) {
            $auditId = (string) ($event['audit_id'] ?? '');
            if ($auditId === '') {
                continue;
            }
            $runs[$auditId] ??= ['audit_id' => $auditId, 'status' => 'running'];
            if ($event['event'] === 'audit_requested') {
                $runs[$auditId]['requested_at'] = (string) $event['timestamp'];
                $runs[$auditId]['target'] = (string) ($event['target'] ?? '');
            }
            if ($event['event'] === 'audit_completed') {
                $runs[$auditId] = [
                    ...$runs[$auditId],
                    'status' => 'completed',
                    'completed_at' => (string) $event['timestamp'],
                    'target' => (string) ($event['target'] ?? $runs[$auditId]['target'] ?? ''),
                    'score' => (int) ($event['score'] ?? 0),
                    'pages_crawled' => (int) ($event['pages_crawled'] ?? 0),
                    'duration_ms' => (int) ($event['request_duration_ms'] ?? 0),
                    'cache_hit' => (bool) ($event['cache_hit'] ?? false),
                ];
            }
            if ($event['event'] === 'audit_failed') {
                $runs[$auditId] = [
                    ...$runs[$auditId],
                    'status' => 'failed',
                    'completed_at' => (string) $event['timestamp'],
                    'duration_ms' => (int) ($event['request_duration_ms'] ?? 0),
                    'error_type' => (string) ($event['error_type'] ?? ''),
                    'error' => (string) ($event['error'] ?? ''),
                ];
            }
        }

        $runs = array_values($runs);
        usort($runs, static fn (array $left, array $right): int => ($right['requested_at'] ?? '') <=> ($left['requested_at'] ?? ''));

        return $this->json([
            'retention_note' => 'Audit logs are retained for the configured short operational window.',
            'total' => count($runs),
            'returned' => min($limit, count($runs)),
            'items' => array_slice($runs, 0, $limit),
        ]);
    }

    #[McpTool(
        name: 'list_admin_contact_leads',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: List recent private consultation requests, including contact details and messages. Requires an Authorization: Bearer header.'
    )]
    public function contactLeads(
        #[Schema(description: 'Maximum records to return, from 1 to 100.')] int $limit = 50,
    ): string {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }
        if (!$this->validLimit($limit)) {
            return $this->invalidLimit();
        }

        $all = $this->leads->all();

        return $this->json([
            'total' => count($all),
            'returned' => min($limit, count($all)),
            'items' => array_map(static fn (Lead $lead): array => [
                'created_at' => $lead->createdAt->format(DATE_ATOM),
                'email' => $lead->email,
                'phone' => $lead->phone,
                'message' => $lead->message,
                'source' => $lead->source,
            ], array_slice($all, 0, $limit)),
            'privacy' => 'Admin-only personal data. Do not log, republish, or forward without a valid purpose.',
        ]);
    }

    #[McpTool(
        name: 'list_admin_product_requests',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: List recent requests for products to add to the used-price index. Requires an Authorization: Bearer header.'
    )]
    public function productRequests(
        #[Schema(description: 'Maximum records to return, from 1 to 100.')] int $limit = 50,
    ): string {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }
        if (!$this->validLimit($limit)) {
            return $this->invalidLimit();
        }

        $all = $this->productRequests->all();

        return $this->json([
            'total' => count($all),
            'returned' => min($limit, count($all)),
            'items' => array_map(static fn (array $request): array => [
                'created_at' => $request['timestamp'],
                'product' => $request['product'],
                'email' => $request['email'],
            ], array_slice($all, 0, $limit)),
            'privacy' => 'Admin-only submission data. Do not log or republish contact details.',
        ]);
    }

    #[McpTool(
        name: 'list_admin_price_tips',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: List active community-submitted public listing links awaiting manual price review. Requires an Authorization: Bearer header.'
    )]
    public function priceTips(
        #[Schema(description: 'Maximum records to return, from 1 to 100.')] int $limit = 50,
    ): string {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }
        if (!$this->validLimit($limit)) {
            return $this->invalidLimit();
        }

        $all = $this->priceTips->all();

        return $this->json([
            'total' => count($all),
            'returned' => min($limit, count($all)),
            'items' => array_map(static fn (PriceTip $tip): array => [
                'submitted_at' => $tip->submittedAt->format(DATE_ATOM),
                'expires_at' => $tip->expiresAt->format(DATE_ATOM),
                'product_slug' => $tip->productSlug,
                'listing_url' => $tip->listingUrl,
                'email' => $tip->email,
            ], array_slice($all, 0, $limit)),
            'privacy' => 'Admin-only review material. Never fetch automatically, log, or republish these URLs.',
        ]);
    }

    private function submissionStats(array $items, callable $date, \DateTimeImmutable $sevenDaysAgo, \DateTimeImmutable $thirtyDaysAgo): array
    {
        return [
            'total' => count($items),
            'last_7_days' => count(array_filter($items, static fn (mixed $item): bool => $date($item) >= $sevenDaysAgo)),
            'last_30_days' => count(array_filter($items, static fn (mixed $item): bool => $date($item) >= $thirtyDaysAgo)),
        ];
    }

    private function auditStatistics(
        array $events,
        \DateTimeImmutable $sevenDaysAgo,
        \DateTimeImmutable $thirtyDaysAgo,
    ): array {
        $requested = array_values(array_filter(
            $events,
            static fn (array $event): bool => $event['event'] === 'audit_requested',
        ));

        return [
            ...$this->submissionStats(
                $requested,
                static fn (array $event): \DateTimeImmutable => new \DateTimeImmutable((string) $event['timestamp']),
                $sevenDaysAgo,
                $thirtyDaysAgo,
            ),
            'completed' => count(array_filter(
                $events,
                static fn (array $event): bool => $event['event'] === 'audit_completed',
            )),
            'failed' => count(array_filter(
                $events,
                static fn (array $event): bool => $event['event'] === 'audit_failed',
            )),
        ];
    }

    /** @param list<string> $values */
    private function frequencies(array $values): array
    {
        $counts = array_count_values(array_filter($values, static fn (string $value): bool => $value !== ''));
        arsort($counts);

        return array_slice($counts, 0, 10, true);
    }

    private function validLimit(int $limit): bool
    {
        return $limit >= 1 && $limit <= 100;
    }

    private function unauthorized(): string
    {
        return $this->json(['error' => 'Unauthorized: valid admin Bearer token required.']);
    }

    private function invalidLimit(): string
    {
        return $this->json(['error' => 'Limit must be between 1 and 100.']);
    }

    private function json(array $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
