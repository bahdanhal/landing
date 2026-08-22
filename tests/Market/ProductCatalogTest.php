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

    public function testConfigurationsAreGroupedIntoFiveDisplayFamilies(): void
    {
        $catalog = new ProductCatalog();
        $families = $catalog->families();

        self::assertCount(5, $families);
        self::assertSame(count($catalog->all()), array_sum(array_map(static fn ($family) => count($family->configurations), $families)));
        self::assertSame(
            ['iphone-13', 'iphone-14', 'macbook-air-13-m1', 'macbook-air-13-m2', 'macbook-air-15-m2'],
            array_map(static fn ($family) => $family->slug, $families),
        );
        foreach ($families as $family) {
            self::assertNotSame('', $family->imageSource);
            self::assertNotSame('', $family->imageCredit);
        }
    }
}
