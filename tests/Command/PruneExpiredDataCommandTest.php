<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Analytics\Domain\PageView;
use App\Analytics\Infrastructure\DoctrinePageViewRepository;
use App\Audit\Infrastructure\AuditLogger;
use App\Command\PruneExpiredDataCommand;
use App\Market\Infrastructure\DoctrinePriceTipRepository;
use App\Tests\DoctrineTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PruneExpiredDataCommandTest extends DoctrineTestCase
{
    private string $tempLogDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempLogDir = sys_get_temp_dir() . '/audit-test-prune-' . bin2hex(random_bytes(4));
        @mkdir($this->tempLogDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach (glob($this->tempLogDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tempLogDir);
    }

    public function testExecutePrunesAllStores(): void
    {
        $pageViewRepo = new DoctrinePageViewRepository($this->entityManager, 30);
        $now = new \DateTimeImmutable('2026-08-23T12:00:00+00:00');
        $oldView = new PageView(
            $now->modify('-45 days'),
            'old-visitor',
            '/',
            'direct',
            null
        );
        $recentView = new PageView(
            $now->modify('-5 days'),
            'recent-visitor',
            '/tools',
            'direct',
            null
        );
        $pageViewRepo->save($oldView);
        $pageViewRepo->save($recentView);

        $priceTipRepo = new DoctrinePriceTipRepository($this->entityManager, 'test-secret');

        // Create expired log file
        $oldLogFile = $this->tempLogDir . '/audit-2025-01-01.jsonl';
        file_put_contents($oldLogFile, "test\n");
        touch($oldLogFile, time() - (40 * 86400));

        $auditLogger = new AuditLogger($this->tempLogDir, 30);

        $command = new PruneExpiredDataCommand($pageViewRepo, $priceTipRepo, $auditLogger);
        $tester = new CommandTester($command);

        $statusCode = $tester->execute([]);

        self::assertSame(0, $statusCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('1 page view(s)', $display);
        self::assertStringContainsString('0 price tip(s)', $display);
        self::assertStringContainsString('1 audit log file(s)', $display);
    }

    public function testExecuteWorksWithGenericRepositoriesViaInterface(): void
    {
        $pageViewRepo = $this->createMock(\App\Analytics\Domain\PageViewRepository::class);
        $pageViewRepo->expects(self::once())
            ->method('prune')
            ->willReturn(5);

        $priceTipRepo = $this->createMock(\App\Market\Domain\PriceTipRepository::class);
        $priceTipRepo->expects(self::once())
            ->method('pruneExpired')
            ->willReturn(3);

        $auditLogger = new AuditLogger($this->tempLogDir, 30);

        $command = new PruneExpiredDataCommand($pageViewRepo, $priceTipRepo, $auditLogger);
        $tester = new CommandTester($command);

        $statusCode = $tester->execute([]);

        self::assertSame(0, $statusCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('5 page view(s)', $display);
        self::assertStringContainsString('3 price tip(s)', $display);
    }
}
