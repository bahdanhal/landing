<?php

namespace App\Command;

use App\Market\Application\ObserveMarket;
use App\Market\Application\ProductCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        $this
            ->addArgument('slug', InputArgument::OPTIONAL, 'One product slug; omit to update the complete catalog.')
            ->addOption('families', null, InputOption::VALUE_NONE, 'Observe the default configuration for each displayed product family.')
            ->addOption('at', null, InputOption::VALUE_REQUIRED, 'Observation date in YYYY-MM-DD format. Past dates require archived evidence.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $requested = $input->getArgument('slug');
        $products = is_string($requested) && $requested !== ''
            ? [$this->catalog->get($requested)]
            : ($input->getOption('families') ? $this->catalog->familyDefaults() : $this->catalog->all());
        if (in_array(null, $products, true)) {
            $output->writeln('<error>Unknown product slug.</error>');
            return Command::INVALID;
        }
        $at = $this->observationDate($input->getOption('at'));
        $failures = 0;
        foreach ($products as $product) {
            try {
                $observation = $this->observeMarket->observe($product->slug, $at);
                $output->writeln(sprintf('<info>%s: %.2f PLN (%s, n=%d, %s)</info>', $product->name, $observation->medianGrosz / 100, $observation->confidence, $observation->sampleSize, $observation->observedAt->format('Y-m-d')));
            } catch (\Throwable $exception) {
                ++$failures;
                $output->writeln(sprintf('<error>%s: %s</error>', $product->name, $exception->getMessage()));
            }
        }

        return $failures === count($products) ? Command::FAILURE : Command::SUCCESS;
    }

    private function observationDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new \InvalidArgumentException('--at must use YYYY-MM-DD.');
        }

        return new \DateTimeImmutable($value.' 12:00:00', new \DateTimeZone('Europe/Warsaw'));
    }
}
