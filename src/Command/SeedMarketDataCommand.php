<?php

declare(strict_types=1);

namespace App\Command;

use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Domain\Product;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:market:seed', description: 'Seed baseline historical market observations for the past 2 weeks.')]
final class SeedMarketDataCommand extends Command
{
    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly PriceObservationRepository $observations,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dates = [
            new \DateTimeImmutable('2026-08-08 12:00:00', new \DateTimeZone('Europe/Warsaw')),
            new \DateTimeImmutable('2026-08-15 12:00:00', new \DateTimeZone('Europe/Warsaw')),
            new \DateTimeImmutable('2026-08-22 12:00:00', new \DateTimeZone('Europe/Warsaw')),
        ];

        $count = 0;
        foreach ($this->catalog->all() as $product) {
            $basePrice = $this->calculateBasePricePln($product);
            if ($basePrice <= 0) {
                continue;
            }

            foreach ($dates as $index => $date) {
                // Slight realistic historical trend (-1% to -3% over 2 weeks)
                $factor = match ($index) {
                    0 => 1.03, // 2 weeks ago
                    1 => 1.015, // 1 week ago
                    default => 1.00, // current
                };

                $medianPln = (int) round($basePrice * $factor);
                $lowPln = (int) round($medianPln * 0.88);
                $highPln = (int) round($medianPln * 1.14);

                $observation = new PriceObservation(
                    $product->slug,
                    $date,
                    $medianPln * 100,
                    $lowPln * 100,
                    $highPln * 100,
                    $index === 2 ? 8 : 6,
                    'high',
                    '',
                    $index < 2 ? PriceObservation::METHODOLOGY_RETROSPECTIVE : PriceObservation::METHODOLOGY_CURRENT
                );

                $this->observations->save($observation);
                ++$count;
            }
        }

        $output->writeln(sprintf('<info>Successfully seeded %d observations across all catalog products.</info>', $count));

        return Command::SUCCESS;
    }

    private function calculateBasePricePln(Product $product): int
    {
        if ($product->category === 'cars') {
            return str_contains($product->slug, '2-0') ? 10500 : 8500;
        }

        if ($product->category === 'ram') {
            return $this->ramPrice($product);
        }

        if ($product->category === 'smartphones') {
            return $this->iphonePrice($product);
        }

        if ($product->category === 'laptops') {
            return $this->macBookPrice($product);
        }

        return 1000;
    }

    private function ramPrice(Product $product): int
    {
        $specs = $product->specifications;
        $cap = $specs['capacity'] ?? '';
        $type = $specs['type'] ?? 'DDR4';
        $isLaptop = str_contains($specs['form_factor'] ?? '', 'SO-DIMM');

        if ($type === 'DDR4') {
            if (str_contains($cap, '16 GB')) {
                return $isLaptop ? 115 : 120;
            }
            if (str_contains($cap, '32 GB')) {
                return str_contains($specs['speed'] ?? '', '3600') ? 250 : 220;
            }
            if (str_contains($cap, '64 GB')) {
                return 480;
            }
        } else {
            // DDR5
            if (str_contains($cap, '16 GB')) {
                return 160;
            }
            if (str_contains($cap, '32 GB')) {
                return str_contains($specs['speed'] ?? '', '6000') ? 390 : 340;
            }
            if (str_contains($cap, '64 GB')) {
                return 740;
            }
        }

        return 200;
    }

    private function iphonePrice(Product $product): int
    {
        $specs = $product->specifications;
        $gen = $specs['generation'] ?? '';
        $variant = $specs['variant'] ?? 'Standard';
        $storage = $specs['storage'] ?? '128 GB';

        $baseByGen = [
            'iPhone X' => 650,
            'iPhone XR' => 750,
            'iPhone XS' => 800,
            'iPhone SE 2020' => 650,
            'iPhone 11' => 1050,
            'iPhone SE 2022' => 1050,
            'iPhone 12' => 1350,
            'iPhone 13' => 1850,
            'iPhone 14' => 2250,
            'iPhone 15' => 2850,
            'iPhone 16' => 3450,
        ];

        $price = $baseByGen[$gen] ?? 1500;

        if ($variant === 'Mini') {
            $price = (int) round($price * 0.88);
        } elseif ($variant === 'Plus') {
            $price = (int) round($price * 1.15);
        } elseif ($variant === 'Pro') {
            $price = (int) round($price * 1.35);
        } elseif ($variant === 'Pro Max' || $variant === 'Max') {
            $price = (int) round($price * 1.55);
        }

        if (str_contains($storage, '128 GB')) {
            $price += 100;
        } elseif (str_contains($storage, '256 GB')) {
            $price += 250;
        } elseif (str_contains($storage, '512 GB')) {
            $price += 500;
        } elseif (str_contains($storage, '1 TB')) {
            $price += 800;
        }

        return $price;
    }

    private function macBookPrice(Product $product): int
    {
        $specs = $product->specifications;
        $line = $specs['line'] ?? 'MacBook Air';
        $chip = $specs['chip'] ?? 'M1';
        $display = $specs['display'] ?? '13-inch';
        $memory = $specs['memory'] ?? '8 GB';
        $storage = $specs['storage'] ?? '256 GB';

        if ($line === 'MacBook Air') {
            $base = match ($chip) {
                'M1' => 2600,
                'M2' => ($display === '15-inch' ? 4400 : 3650),
                'M3' => ($display === '15-inch' ? 5100 : 4450),
                default => 3000,
            };
        } else {
            // MacBook Pro
            $base = match ($chip) {
                'M1 Pro' => ($display === '16-inch' ? 5500 : 4800),
                'M1 Max' => ($display === '16-inch' ? 6800 : 6500),
                'M2 Pro' => ($display === '16-inch' ? 7100 : 6200),
                'M2 Max' => ($display === '16-inch' ? 8600 : 7600),
                'M3' => 5400,
                'M3 Pro' => ($display === '16-inch' ? 8800 : 7600),
                'M3 Max' => ($display === '16-inch' ? 11500 : 10500),
                default => 6000,
            };
        }

        // Memory adjustment
        if (str_contains($memory, '16 GB') || str_contains($memory, '18 GB')) {
            $base += 600;
        } elseif (str_contains($memory, '24 GB')) {
            $base += 1100;
        } elseif (str_contains($memory, '32 GB') || str_contains($memory, '36 GB')) {
            $base += 1500;
        } elseif (str_contains($memory, '48 GB') || str_contains($memory, '64 GB')) {
            $base += 2400;
        }

        // Storage adjustment
        if (str_contains($storage, '512 GB')) {
            $base += 450;
        } elseif (str_contains($storage, '1 TB')) {
            $base += 950;
        } elseif (str_contains($storage, '2 TB')) {
            $base += 1800;
        }

        return $base;
    }
}
