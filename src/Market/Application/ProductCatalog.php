<?php

namespace App\Market\Application;

use App\Market\Domain\Product;
use App\Market\Domain\ProductFamily;

final class ProductCatalog
{
    /** @return list<Product> */
    public function all(): array
    {
        return [...$this->iphones(), ...$this->macBooks(), ...$this->cars()];
    }

    public function get(string $slug): ?Product
    {
        foreach ($this->all() as $product) {
            if ($product->slug === $slug) {
                return $product;
            }
        }

        return null;
    }

    /** @return list<ProductFamily> */
    public function families(): array
    {
        $families = [];
        foreach ($this->all() as $product) {
            $families[$this->familySlug($product)][] = $product;
        }

        return array_map(function (array $products): ProductFamily {
            $first = $products[0];
            $familySlug = $this->familySlug($first);
            $name = match ($first->category) {
                'smartphones' => 'Apple '.$first->specifications['generation'],
                'laptops' => sprintf('MacBook Air %s %s', $first->specifications['display'], $first->specifications['chip']),
                'cars' => 'Peugeot 206 CC',
            };
            [$image, $credit, $source] = match ($familySlug) {
                'iphone-13' => ['/images/market/iphone-13.jpg', 'Kskhh', 'https://commons.wikimedia.org/wiki/File:IPhone_13.jpg'],
                'iphone-14' => ['/images/market/iphone-14-plus.jpg', 'Kskhh', 'https://commons.wikimedia.org/wiki/File:IPhone_13_and_iPhone_14_Plus.jpg'],
                'macbook-air-13-m1' => ['/images/market/macbook-air-m1.png', 'L', 'https://commons.wikimedia.org/wiki/File:Macbook_Air_M1_Silver_PNG.png'],
                'macbook-air-13-m2' => ['/images/market/macbook-air-m2.jpg', 'KKPCW (Kyu3)', 'https://commons.wikimedia.org/wiki/File:M2_Macbook_Air_Midnight_model_-_1.jpg'],
                'macbook-air-15-m2' => ['/images/market/macbook-air-15.jpg', 'KKPCW (Kyu3)', 'https://commons.wikimedia.org/wiki/File:Macbook_Air_15_inch_-_1.jpg'],
                'peugeot-206-cc' => ['/images/market/peugeot-206-cc.jpg', 'Corvettec6r', 'https://commons.wikimedia.org/wiki/File:Peugeot_206_CC.jpg'],
            };

            return new ProductFamily(
                $familySlug,
                $name,
                $first->category,
                $image,
                $credit,
                $source,
                $products,
            );
        }, array_values($families));
    }

    /** @return list<Product> */
    public function familyDefaults(): array
    {
        return array_map(static fn (ProductFamily $family): Product => $family->defaultConfiguration(), $this->families());
    }

    private function familySlug(Product $product): string
    {
        if ($product->category === 'smartphones') {
            return strtolower(str_replace(' ', '-', $product->specifications['generation']));
        }

        if ($product->category === 'cars') {
            return 'peugeot-206-cc';
        }

        return strtolower(str_replace([' ', '-inch'], ['-', ''], sprintf('macbook-air-%s-%s', $product->specifications['display'], $product->specifications['chip'])));
    }

    /** @return list<Product> */
    private function iphones(): array
    {
        $products = [];
        foreach (['13', '14'] as $generation) {
            foreach (['' => ['128', '256', '512'], 'plus' => ['128', '256', '512'], 'pro' => ['128', '256', '512', '1tb'], 'pro-max' => ['128', '256', '512', '1tb']] as $variant => $capacities) {
                if ($generation === '13' && $variant === 'plus') {
                    continue;
                }
                if ($generation === '13') {
                    $capacitiesByVariant = $variant === '' ? ['128', '256', '512'] : $capacities;
                } else {
                    $capacitiesByVariant = $capacities;
                }
                foreach ($capacitiesByVariant as $capacity) {
                    $variantName = $variant === '' ? '' : ' '.ucwords(str_replace('-', ' ', $variant));
                    $storage = $capacity === '1tb' ? '1 TB' : $capacity.' GB';
                    $name = sprintf('Apple iPhone %s%s %s', $generation, $variantName, $storage);
                    $slug = sprintf('iphone-%s%s-%s', $generation, $variant === '' ? '' : '-'.$variant, str_replace(' ', '', strtolower($storage)));
                    $products[] = new Product($slug, $name, sprintf('Unlocked %s, used and fully functional, with intact screen. Include comparable Polish asking prices and exclude new, damaged, parts-only, locked, bundled and refurbished-as-new units.', $name), 'smartphones', ['generation' => 'iPhone '.$generation, 'variant' => trim($variantName) ?: 'Standard', 'storage' => $storage]);
                }
            }
        }

        return $products;
    }

    /** @return list<Product> */
    private function macBooks(): array
    {
        $products = [];
        $families = [
            ['chip' => 'M1', 'display' => '13-inch', 'memory' => ['8 GB', '16 GB'], 'storage' => ['256 GB', '512 GB', '1 TB', '2 TB']],
            ['chip' => 'M2', 'display' => '13-inch', 'memory' => ['8 GB', '16 GB', '24 GB'], 'storage' => ['256 GB', '512 GB', '1 TB', '2 TB']],
            ['chip' => 'M2', 'display' => '15-inch', 'memory' => ['8 GB', '16 GB', '24 GB'], 'storage' => ['256 GB', '512 GB', '1 TB', '2 TB']],
        ];
        foreach ($families as $family) {
            foreach ($family['memory'] as $memory) {
                foreach ($family['storage'] as $storage) {
                    $name = sprintf('MacBook Air %s %s · %s RAM · %s SSD', $family['display'], $family['chip'], $memory, $storage);
                    $slug = strtolower(str_replace([' · ', ' ', '-inch'], ['-', '-', ''], $name));
                    $products[] = new Product($slug, $name, sprintf('Used, fully functional Apple %s in Poland with the exact display, chip, unified-memory and SSD configuration shown. Exclude damaged, parts-only, locked, bundled and refurbished-as-new units.', $name), 'laptops', ['display' => $family['display'], 'chip' => $family['chip'], 'memory' => $memory, 'storage' => $storage]);
                }
            }
        }

        return $products;
    }

    /** @return list<Product> */
    private function cars(): array
    {
        return [
            new Product(
                'peugeot-206-cc-1-6-petrol',
                'Peugeot 206 CC 1.6 petrol',
                'Used, registered and roadworthy Peugeot 206 CC with the 1.6-litre petrol engine in Poland. Include complete running cars with normal age-related wear. Exclude damaged, parts-only, non-running, heavily modified, imported-unregistered and dealer-new vehicles.',
                'cars',
                ['model' => '206 CC', 'engine' => '1.6 petrol', 'market' => 'Poland'],
            ),
            new Product(
                'peugeot-206-cc-2-0-petrol',
                'Peugeot 206 CC 2.0 petrol',
                'Used, registered and roadworthy Peugeot 206 CC with the 2.0-litre petrol engine in Poland. Include complete running cars with normal age-related wear. Exclude damaged, parts-only, non-running, heavily modified, imported-unregistered and dealer-new vehicles.',
                'cars',
                ['model' => '206 CC', 'engine' => '2.0 petrol', 'market' => 'Poland'],
            ),
        ];
    }
}
