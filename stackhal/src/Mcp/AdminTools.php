<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Analytics\Application\TrafficAnalytics;
use App\Audit\Infrastructure\AuditLogger;
use App\Lead\Domain\Lead;
use App\Lead\Domain\LeadRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class AdminTools
{
    public function __construct(
        private AdminAccess $access,
        private LeadRepository $leads,
        private AuditLogger $auditLogger,
        private TrafficAnalytics $trafficAnalytics,
    ) {
    }

    #[McpTool(
        name: 'get_admin_dashboard_statistics',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Admin-only: Get privacy-preserving traffic, submission, and SEO audit statistics. Requires an Authorization: Bearer header.'
    )]
    public function statistics(): string
    {
        if (!$this->access->isGranted()) {
            return $this->unauthorized();
        }

        $leads = $this->leads->all();
        $auditEvents = $this->auditLogger->events();
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
            'seo_audits' => $this->auditStatistics($auditEvents, $sevenDaysAgo, $thirtyDaysAgo),
            'traffic' => $this->trafficAnalytics->summary($now),
            'lead_sources' => $this->frequencies(array_map(static fn (Lead $lead): string => $lead->source, $leads)),
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
     * @param list<array<string, mixed>> $events
     * @return array{total: int, last_7_days: int, last_30_days: int, completed: int, failed: int}
     */
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
