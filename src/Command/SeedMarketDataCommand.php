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
            return str_contains($product->slug, '2-0') ? 4900 : 3800;
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

        return 500;
    }

    private function ramPrice(Product $product): int
    {
        $specs = $product->specifications;
        $cap = $specs['capacity'] ?? '';
        $type = $specs['type'] ?? 'DDR4';
        $isLaptop = str_contains($specs['form_factor'] ?? '', 'SO-DIMM');
        $speed = $specs['speed'] ?? '';

        if ($type === 'DDR4') {
            if ($cap === '8 GB') {
                return $isLaptop ? 480 : 500;
            }
            if ($cap === '16 GB') {
                if ($isLaptop) {
                    return 960;
                }
                return str_contains($speed, '3600') ? 1100 : 1000;
            }
            if ($cap === '32 GB') {
                return $isLaptop ? 1250 : 1350;
            }
        } else {
            // DDR5
            if ($cap === '8 GB') {
                return 520;
            }
            if ($cap === '16 GB') {
                if ($isLaptop) {
                    return str_contains($speed, '5600') ? 980 : 920;
                }
                return str_contains($speed, '6000') ? 950 : 880;
            }
            if ($cap === '32 GB') {
                return $isLaptop ? 1800 : 1850;
            }
        }

        return 500;
    }

    private function iphonePrice(Product $product): int
    {
        $specs = $product->specifications;
        $gen = $specs['generation'] ?? '';
        $variant = $specs['variant'] ?? 'Standard';
        $storage = $specs['storage'] ?? '128 GB';

        // Base price for lowest available capacity of each model
        $basePrices = [
            'iPhone X' => ['Standard' => 220],
            'iPhone XR' => ['Standard' => 280],
            'iPhone XS' => ['Standard' => 310, 'Max' => 400],
            'iPhone SE 2020' => ['Standard' => 260],
            'iPhone 11' => ['Standard' => 420, 'Pro' => 620, 'Pro Max' => 750],
            'iPhone SE 2022' => ['Standard' => 450],
            'iPhone 12' => ['Mini' => 520, 'Standard' => 650, 'Pro' => 900, 'Pro Max' => 1100],
            'iPhone 13' => ['Mini' => 850, 'Standard' => 950, 'Pro' => 1300, 'Pro Max' => 1550],
            'iPhone 14' => ['Standard' => 1250, 'Plus' => 1400, 'Pro' => 1750, 'Pro Max' => 2050],
            'iPhone 15' => ['Standard' => 1650, 'Plus' => 1850, 'Pro' => 2300, 'Pro Max' => 2750],
            'iPhone 16' => ['Standard' => 2200, 'Plus' => 2450, 'Pro' => 2950, 'Pro Max' => 3500],
        ];

        $variantKey = match ($variant) {
            'Mini' => 'Mini',
            'Plus' => 'Plus',
            'Pro' => 'Pro',
            'Pro Max', 'Max' => (isset($basePrices[$gen]['Pro Max']) ? 'Pro Max' : 'Max'),
            default => 'Standard',
        };

        $price = $basePrices[$gen][$variantKey] ?? 800;

        // Storage increments relative to base tier
        // iPhone 15 Pro Max and 16 Pro Max start at 256 GB base
        $startsAt256 = ($gen === 'iPhone 15' || $gen === 'iPhone 16') && ($variant === 'Pro Max');
        // Models starting at 128 GB
        $startsAt128 = in_array($gen, ['iPhone 13', 'iPhone 14', 'iPhone 15', 'iPhone 16'], true)
            || (($gen === 'iPhone 12') && ($variant === 'Pro' || $variant === 'Pro Max'));

        if ($startsAt256) {
            if (str_contains($storage, '512 GB')) {
                $price += 250;
            } elseif (str_contains($storage, '1 TB')) {
                $price += 500;
            }
        } elseif ($startsAt128) {
            if (str_contains($storage, '256 GB')) {
                $price += 100;
            } elseif (str_contains($storage, '512 GB')) {
                $price += 200;
            } elseif (str_contains($storage, '1 TB')) {
                $price += 360;
            }
        } else {
            // Models starting at 64 GB
            if (str_contains($storage, '128 GB')) {
                $price += 50;
            } elseif (str_contains($storage, '256 GB')) {
                $price += 90;
            } elseif (str_contains($storage, '512 GB')) {
                $price += 160;
            }
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
                'M1' => 1350,
                'M2' => ($display === '15-inch' ? 2650 : 2150),
                'M3' => ($display === '15-inch' ? 3300 : 2800),
                default => 1500,
            };

            // Memory additions for MacBook Air (base is 8 GB)
            if (str_contains($memory, '16 GB')) {
                $base += ($chip === 'M3' ? 400 : 300);
            } elseif (str_contains($memory, '24 GB')) {
                $base += ($chip === 'M3' ? 800 : 700);
            }

            // Storage additions for MacBook Air (base is 256 GB)
            if (str_contains($storage, '512 GB')) {
                $base += ($chip === 'M3' ? 350 : 250);
            } elseif (str_contains($storage, '1 TB')) {
                $base += ($chip === 'M3' ? 700 : 500);
            } elseif (str_contains($storage, '2 TB')) {
                $base += ($chip === 'M3' ? 1150 : 900);
            }

            return $base;
        }

        // MacBook Pro models
        // Base configurations:
        // - M1 Pro / M2 Pro / M3 Pro: Base is 16 GB (or 18 GB) RAM and 512 GB SSD
        // - M1 Max / M2 Max / M3 Max (14"): Base is 32 GB (or 36 GB) RAM and 512 GB / 1 TB SSD
        // - M1 Max / M2 Max / M3 Max (16"): Base is 32 GB (or 36 GB) RAM and 1 TB SSD
        // - M3 14": Base is 8 GB RAM and 512 GB SSD
        $base = match ($chip) {
            'M1 Pro' => ($display === '16-inch' ? 3100 : 2700),
            'M1 Max' => ($display === '16-inch' ? 4100 : 3500),
            'M2 Pro' => ($display === '16-inch' ? 5100 : 4600),
            'M2 Max' => ($display === '16-inch' ? 6500 : 5600),
            'M3' => 3700,
            'M3 Pro' => ($display === '16-inch' ? 5900 : 5200),
            'M3 Max' => ($display === '16-inch' ? 8200 : 7200),
            default => 3500,
        };

        // Memory additions above base tier for each chip
        if ($chip === 'M3') {
            // M3 14" starts at 8 GB
            if (str_contains($memory, '16 GB')) {
                $base += 450;
            } elseif (str_contains($memory, '24 GB')) {
                $base += 900;
            }
        } elseif (in_array($chip, ['M1 Pro', 'M2 Pro'], true)) {
            // M1/M2 Pro starts at 16 GB
            if (str_contains($memory, '32 GB')) {
                $base += ($chip === 'M2 Pro' ? 800 : 450);
            }
        } elseif ($chip === 'M3 Pro') {
            // M3 Pro starts at 18 GB
            if (str_contains($memory, '36 GB')) {
                $base += 700;
            }
        } elseif (in_array($chip, ['M1 Max', 'M2 Max'], true)) {
            // M1/M2 Max starts at 32 GB
            if (str_contains($memory, '64 GB')) {
                $base += ($chip === 'M2 Max' ? 700 : 550);
            }
        } elseif ($chip === 'M3 Max') {
            // M3 Max starts at 36 GB
            if (str_contains($memory, '48 GB')) {
                $base += 600;
            } elseif (str_contains($memory, '64 GB')) {
                $base += 1000;
            }
        }

        // Storage additions above base tier for each model
        $startsAt1Tb = ($display === '16-inch' && in_array($chip, ['M1 Max', 'M2 Max', 'M3 Max'], true))
            || ($display === '14-inch' && in_array($chip, ['M2 Max', 'M3 Max'], true));

        if ($startsAt1Tb) {
            // Base SSD is 1 TB
            if (str_contains($storage, '2 TB')) {
                $base += 500;
            }
        } else {
            // Base SSD is 512 GB
            if (str_contains($storage, '1 TB')) {
                $base += ($chip === 'M3 Max' ? 500 : 400);
            } elseif (str_contains($storage, '2 TB')) {
                $base += 850;
            }
        }

        return $base;
    }
}
