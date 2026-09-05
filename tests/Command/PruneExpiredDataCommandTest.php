<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Analytics\Domain\PageViewRepository;
use App\Command\PruneExpiredDataCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PruneExpiredDataCommandTest extends TestCase
{
    public function testExecutePrunesPageViewsAndAiInteractions(): void
    {
        $pageViewRepo = $this->createMock(PageViewRepository::class);
        $pageViewRepo->expects(self::once())
            ->method('prune')
            ->willReturn(5);

        $aiRepo = $this->createMock(\App\Analytics\Domain\AiInteractionRepository::class);
        $aiRepo->expects(self::once())
            ->method('prune')
            ->willReturn(3);

        $command = new PruneExpiredDataCommand($pageViewRepo, $aiRepo);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('5 page view(s)', $tester->getDisplay());
        self::assertStringContainsString('3 AI interaction(s)', $tester->getDisplay());
    }
}
