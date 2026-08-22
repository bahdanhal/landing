<?php

namespace App\Market\Application;

use App\Market\Domain\Product;

final class ProductCatalog
{
    /** @return list<Product> */
    public function all(): array
    {
        return [new Product('iphone-14-128gb', 'Apple iPhone 14 128 GB', 'Unlocked Apple iPhone 14 with 128 GB storage, used and fully functional, no cracked screen or parts-only units. Include private and professional asking prices in Poland; exclude new, refurbished-as-new, damaged and bundled listings.', 'smartphones')];
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
}
