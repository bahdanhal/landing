<?php

namespace App\Tests\Market;

use App\Market\Application\ProductCatalog;
use PHPUnit\Framework\TestCase;

final class ProductCatalogTest extends TestCase
{
    public function testCatalogContainsStableIphoneAndMacBookConfigurations(): void
    {
        $catalog = new ProductCatalog();

        self::assertNotNull($catalog->get('iphone-14-128gb'));
        self::assertNotNull($catalog->get('iphone-14-pro-max-1tb'));
        self::assertNotEmpty(array_filter($catalog->all(), static fn ($product) => $product->category === 'laptops'));
        self::assertSame(count($catalog->all()), count(array_unique(array_map(static fn ($product) => $product->slug, $catalog->all()))));
    }
}
