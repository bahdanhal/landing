<?php

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
}
