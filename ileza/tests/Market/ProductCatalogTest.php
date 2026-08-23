<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\ProductCatalog;
use PHPUnit\Framework\TestCase;

final class ProductCatalogTest extends TestCase
{
    public function testPeugeotFamilyContainsBothEngineVariants(): void
    {
        $catalog = new ProductCatalog();
        $family = array_values(array_filter($catalog->families(), static fn ($item) => $item->slug === 'peugeot-206-cc'))[0] ?? null;

        self::assertNotNull($family);
        self::assertSame('Peugeot 206 CC', $family->name);
        self::assertCount(2, $family->configurations);
        self::assertSame('peugeot-206-cc-1-6-petrol', $family->defaultConfiguration()->slug);
    }

    public function testFindsFamilyByConfigurationSlug(): void
    {
        $catalog = new ProductCatalog();
        $family = $catalog->familyFor('iphone-13-128gb');

        self::assertNotNull($family);
        self::assertSame('iphone-13', $family->slug);
        self::assertNull($catalog->familyFor('non-existent-slug'));
    }

    public function testContainsAllIphoneGenerationsFromXTo16(): void
    {
        $catalog = new ProductCatalog();
        $expectedGenerations = [
            'iphone-x',
            'iphone-xr',
            'iphone-xs',
            'iphone-11',
            'iphone-se-2020',
            'iphone-12',
            'iphone-13',
            'iphone-se-2022',
            'iphone-14',
            'iphone-15',
            'iphone-16',
        ];

        $familySlugs = array_map(static fn ($f) => $f->slug, $catalog->families());
        foreach ($expectedGenerations as $expected) {
            self::assertContains($expected, $familySlugs, "Expected family {$expected} to exist in catalog.");
        }
    }

    public function testContainsMacbookProFamilies(): void
    {
        $catalog = new ProductCatalog();
        $familySlugs = array_map(static fn ($f) => $f->slug, $catalog->families());

        self::assertContains('macbook-pro-14-m1-pro', $familySlugs);
        self::assertContains('macbook-pro-16-m1-max', $familySlugs);
        self::assertContains('macbook-pro-14-m3-pro', $familySlugs);
    }

    public function testContainsRamMemoryKits(): void
    {
        $catalog = new ProductCatalog();
        $familySlugs = array_map(static fn ($f) => $f->slug, $catalog->families());

        self::assertContains('ram-ddr4-desktop', $familySlugs);
        self::assertContains('ram-ddr5-desktop', $familySlugs);
        self::assertContains('ram-ddr4-laptop', $familySlugs);
        self::assertContains('ram-ddr5-laptop', $familySlugs);
    }

    public function testAllProductsHaveValidUrlSafeSlugsAndNonEmptyImages(): void
    {
        $catalog = new ProductCatalog();
        $products = $catalog->all();
        self::assertNotEmpty($products);

        $slugs = [];
        foreach ($products as $product) {
            self::assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $product->slug);
            self::assertNotEmpty($product->name);
            self::assertNotEmpty($product->definition);
            self::assertNotContains($product->slug, $slugs, "Duplicate slug found: {$product->slug}");
            $slugs[] = $product->slug;
        }

        foreach ($catalog->families() as $family) {
            self::assertNotEmpty($family->image);
            self::assertNotEmpty($family->imageCredit);
            self::assertNotEmpty($family->imageSource);
            self::assertNotEmpty($family->defaultConfiguration()->slug);
            self::assertStringStartsNotWith('/images/market/iphone-device.svg', $family->image);
            self::assertStringStartsNotWith('/images/market/macbook-pro.svg', $family->image);
            self::assertStringStartsNotWith('/images/market/ram-module.svg', $family->image);

            $publicPath = dirname(__DIR__, 2) . '/public' . $family->image;
            self::assertFileExists($publicPath, "Image file {$family->image} for family {$family->slug} must exist in public directory.");
        }
    }
}
