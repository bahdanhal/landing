<?php

declare(strict_types=1);

namespace App\Command;

use App\Analytics\Domain\AiInteractionRepository;
use App\Analytics\Domain\PageViewRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:prune-expired-data',
    description: 'Prune expired analytics page views and AI telemetry.'
)]
final class PruneExpiredDataCommand extends Command
{
    public function __construct(
        private readonly PageViewRepository $pageViewRepository,
        private readonly ?AiInteractionRepository $aiInteractionRepository = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pruning Expired Data');

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $pageViewsPruned = $this->pageViewRepository->prune($now);
        $aiInteractionsPruned = $this->aiInteractionRepository?->prune($now) ?? 0;

        $io->success(sprintf(
            'Pruning complete: %d page view(s) and %d AI interaction(s) removed.',
            $pageViewsPruned,
            $aiInteractionsPruned
        ));

        return Command::SUCCESS;
    }
}
