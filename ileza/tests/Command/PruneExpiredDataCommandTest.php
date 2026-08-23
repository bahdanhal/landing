<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Analytics\Domain\PageViewRepository;
use App\Command\PruneExpiredDataCommand;
use App\Market\Domain\PriceTipRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PruneExpiredDataCommandTest extends TestCase
{
    public function testExecutePrunesAllStores(): void
    {
        $pageViewRepo = $this->createMock(PageViewRepository::class);
        $pageViewRepo->expects(self::once())
            ->method('prune')
            ->willReturn(5);

        $priceTipRepo = $this->createMock(PriceTipRepository::class);
        $priceTipRepo->expects(self::once())
            ->method('pruneExpired')
            ->willReturn(2);

        $command = new PruneExpiredDataCommand($pageViewRepo, $priceTipRepo);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('5 page view(s)', $tester->getDisplay());
        self::assertStringContainsString('2 price tip(s)', $tester->getDisplay());
    }
}
