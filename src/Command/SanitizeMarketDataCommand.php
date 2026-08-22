<?php

declare(strict_types=1);

namespace App\Command;

use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:market:sanitize', description: 'Normalize stored observations to the current manual-review methodology.')]
final class SanitizeMarketDataCommand extends Command
{
    public function __construct(private readonly ProductCatalog $catalog, private readonly PriceObservationRepository $observations)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = 0;
        foreach ($this->catalog->all() as $product) {
            foreach ($this->observations->history($product->slug) as $observation) {
                $this->observations->save($observation);
                ++$count;
            }
        }
        $output->writeln(sprintf('<info>Sanitized %d market observation(s).</info>', $count));

        return Command::SUCCESS;
    }
}
