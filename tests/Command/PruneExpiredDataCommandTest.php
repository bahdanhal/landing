<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Analytics\Domain\PageViewRepository;
use App\Command\PruneExpiredDataCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PruneExpiredDataCommandTest extends TestCase
{
    public function testExecutePrunesPageViews(): void
    {
        $pageViewRepo = $this->createMock(PageViewRepository::class);
        $pageViewRepo->expects(self::once())
            ->method('prune')
            ->willReturn(5);

        $command = new PruneExpiredDataCommand($pageViewRepo);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('5 page view(s) removed', $tester->getDisplay());
    }
}
