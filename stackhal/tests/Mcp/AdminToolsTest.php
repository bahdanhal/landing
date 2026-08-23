<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Analytics\Application\TrafficAnalytics;
use App\Analytics\Infrastructure\JsonlPageViewRepository;
use App\Audit\Infrastructure\AuditLogger;
use App\Lead\Domain\Lead;
use App\Lead\Domain\LeadRepository;
use App\Lead\Infrastructure\JsonlLeadRepository;
use App\Mcp\AdminAccess;
use App\Mcp\AdminTools;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AdminToolsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/admin-mcp-test-' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testAllAdminToolsFailClosedWithoutBearerToken(): void
    {
        $tools = $this->tools(false);

        $responses = [
            $tools->statistics(),
            $tools->contactLeads(),
            $tools->recentAudits(),
        ];
        foreach ($responses as $json) {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            self::assertStringContainsString('Unauthorized', $data['error']);
        }
    }

    public function testAdminCanReadSubmissionsAndAggregateStatistics(): void
    {
        $leadRepository = new JsonlLeadRepository($this->directory . '/leads');
        $leadRepository->save(Lead::create(
            'person@example.com',
            '+48 500 000 000',
            'Please review my workflow.',
            'hashed-ip',
            'landing',
        ));

        $auditLogger = new AuditLogger($this->directory . '/audits', 14);
        $auditLogger->log('audit_requested', [
            'audit_id' => 'audit-123',
            'target' => 'https://example.com/',
        ]);
        $auditLogger->log('audit_completed', [
            'audit_id' => 'audit-123',
            'target' => 'https://example.com/',
            'score' => 91,
            'pages_crawled' => 4,
            'request_duration_ms' => 1200,
            'cache_hit' => false,
        ]);

        $tools = $this->tools(true, $leadRepository, $auditLogger);
        $statistics = json_decode($tools->statistics(), true, flags: JSON_THROW_ON_ERROR);
        $leads = json_decode($tools->contactLeads(10), true, flags: JSON_THROW_ON_ERROR);
        $audits = json_decode($tools->recentAudits(10), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $statistics['submissions']['contact_leads']['total']);
        self::assertSame(1, $statistics['seo_audits']['completed']);
        self::assertSame(0, $statistics['traffic']['last_30_days']['page_views']);
        self::assertSame('person@example.com', $leads['items'][0]['email']);
        self::assertSame(91, $audits['items'][0]['score']);
        self::assertArrayNotHasKey('ip_hash', $leads['items'][0]);
    }

    public function testAdminListsRejectUnsafeResultLimits(): void
    {
        $tools = $this->tools(true);
        $data = json_decode($tools->contactLeads(101), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('Limit must be between 1 and 100.', $data['error']);
    }

    private function tools(
        bool $authenticated,
        ?LeadRepository $leads = null,
        ?AuditLogger $auditLogger = null,
    ): AdminTools {
        $requestStack = new RequestStack();
        $request = new Request();
        if ($authenticated) {
            $request->headers->set('Authorization', 'Bearer admin-test-token');
        }
        $requestStack->push($request);

        return new AdminTools(
            new AdminAccess($requestStack, 'admin-test-token'),
            $leads ?? new JsonlLeadRepository($this->directory . '/leads'),
            $auditLogger ?? new AuditLogger($this->directory . '/audits', 14),
            new TrafficAnalytics(new JsonlPageViewRepository($this->directory . '/analytics', 90)),
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($directory);
    }
}
