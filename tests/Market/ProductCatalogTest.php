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
}
