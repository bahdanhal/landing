<?php

declare(strict_types=1);

namespace App\Command;

use App\Analytics\Domain\PageViewRepository;
use App\Audit\Infrastructure\AuditLogger;
use App\Market\Domain\PriceTipRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:prune-expired-data',
    description: 'Prune expired analytics page views, price tips, and audit log files.'
)]
final class PruneExpiredDataCommand extends Command
{
    public function __construct(
        private readonly PageViewRepository $pageViewRepository,
        private readonly PriceTipRepository $priceTipRepository,
        private readonly AuditLogger $auditLogger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pruning Expired Data');

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // 1. Prune page views
        $pageViewsPruned = $this->pageViewRepository->prune($now);

        // 2. Prune price tips
        $priceTipsPruned = $this->priceTipRepository->pruneExpired($now);

        // 3. Prune audit logs
        $auditLogsPruned = $this->auditLogger->pruneExpired();

        $io->success(sprintf(
            'Pruning complete: %d page view(s), %d price tip(s), %d audit log file(s) removed.',
            $pageViewsPruned,
            $priceTipsPruned,
            $auditLogsPruned
        ));

        return Command::SUCCESS;
    }
}
