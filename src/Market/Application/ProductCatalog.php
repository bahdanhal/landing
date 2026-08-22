<?php

namespace App\Market\Application;

use App\Market\Domain\Product;

final class ProductCatalog
{
    /** @return list<Product> */
    public function all(): array
    {
        return [...$this->iphones(), ...$this->macBooks()];
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
}
