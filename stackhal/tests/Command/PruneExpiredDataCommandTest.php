<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Analytics\Domain\PageViewRepository;
use App\Audit\Infrastructure\AuditLogger;
use App\Command\PruneExpiredDataCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PruneExpiredDataCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/prune_test_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            @rmdir($this->tempDir);
        }
    }

    public function testExecutePrunesAllStores(): void
    {
        $pageViewRepo = $this->createMock(PageViewRepository::class);
        $pageViewRepo->expects(self::once())
            ->method('prune')
            ->willReturn(5);

        $auditLogger = new AuditLogger($this->tempDir, 14);

        $command = new PruneExpiredDataCommand($pageViewRepo, $auditLogger);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('5 page view(s)', $tester->getDisplay());
        self::assertStringContainsString('0 audit log file(s)', $tester->getDisplay());
    }
}
