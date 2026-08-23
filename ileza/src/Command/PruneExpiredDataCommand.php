<?php

declare(strict_types=1);

namespace App\Command;

use App\Analytics\Domain\PageViewRepository;
use App\Market\Domain\PriceTipRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:prune-expired-data',
    description: 'Prune expired analytics page views and price tips.'
)]
final class PruneExpiredDataCommand extends Command
{
    public function __construct(
        private readonly PageViewRepository $pageViewRepository,
        private readonly PriceTipRepository $priceTipRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pruning Expired Data');

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $pageViewsPruned = $this->pageViewRepository->prune($now);
        $priceTipsPruned = $this->priceTipRepository->pruneExpired($now);

        $io->success(sprintf(
            'Pruning complete: %d page view(s), %d price tip(s) removed.',
            $pageViewsPruned,
            $priceTipsPruned
        ));

        return Command::SUCCESS;
    }
}
