<?php

declare(strict_types=1);

namespace App\Mcp;

use Bahdan\LeadCaptureBundle\Domain\Lead;
use Bahdan\LeadCaptureBundle\Domain\LeadRepository;
use Bahdan\PrivacyAnalyticsBundle\Application\TrafficAnalytics;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class AdminTools
{
    public function __construct(
        private AdminAccess $access,
        private LeadRepository $leads,
        private TrafficAnalytics $trafficAnalytics,
    ) {
    }

    #[McpTool(
        name: 'get_admin_dashboard_statistics',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: Get privacy-preserving traffic and submission statistics. Requires an Authorization: Bearer header.'
    )]
    public function statistics(): string
    {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }

        $leads = $this->leads->all();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $sevenDaysAgo = $now->modify('-7 days');
        $thirtyDaysAgo = $now->modify('-30 days');

        return $this->json([
            'generated_at' => $now->format(DATE_ATOM),
            'submissions' => [
                'contact_leads' => $this->submissionStats(
                    $leads,
                    static fn (Lead $lead): \DateTimeImmutable => $lead->createdAt,
                    $sevenDaysAgo,
                    $thirtyDaysAgo,
                ),
            ],
            'traffic' => $this->trafficAnalytics->summary($now),
            'lead_sources' => $this->frequencies(array_map(static fn (Lead $lead): string => $lead->source, $leads)),
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

    /**
     * @param list<mixed> $items
     * @return array{total: int, last_7_days: int, last_30_days: int}
     */
    private function submissionStats(array $items, callable $date, \DateTimeImmutable $sevenDaysAgo, \DateTimeImmutable $thirtyDaysAgo): array
    {
        return [
            'total' => count($items),
            'last_7_days' => count(array_filter($items, static fn (mixed $item): bool => $date($item) >= $sevenDaysAgo)),
            'last_30_days' => count(array_filter($items, static fn (mixed $item): bool => $date($item) >= $thirtyDaysAgo)),
        ];
    }

    /**
     * @param list<string> $values
     * @return array<string, int>
     */
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

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
