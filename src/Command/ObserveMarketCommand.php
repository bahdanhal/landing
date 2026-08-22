<?php

namespace App\Command;

use App\Market\Application\ObserveMarket;
use App\Market\Application\ProductCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:market:observe', description: 'Research and store weekly Polish second-hand asking-price observations.')]
final class ObserveMarketCommand extends Command
{
    public function __construct(private readonly ObserveMarket $observeMarket, private readonly ProductCatalog $catalog)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('slug', InputArgument::OPTIONAL, 'One product slug; omit to update the complete catalog.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $requested = $input->getArgument('slug');
        $products = is_string($requested) && $requested !== '' ? [$this->catalog->get($requested)] : $this->catalog->all();
        if (in_array(null, $products, true)) {
            $output->writeln('<error>Unknown product slug.</error>');
            return Command::INVALID;
        }
        foreach ($products as $product) {
            $observation = $this->observeMarket->observe($product->slug);
            $output->writeln(sprintf('<info>%s: %.2f PLN (%s, n=%d)</info>', $product->name, $observation->medianGrosz / 100, $observation->confidence, $observation->sampleSize));
        }

        return Command::SUCCESS;
    }
}
