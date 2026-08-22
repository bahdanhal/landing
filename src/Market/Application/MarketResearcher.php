<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;

interface MarketResearcher
{
    public function observe(Product $product, \DateTimeImmutable $at): PriceObservation;

    /** @param list<Product> $products @return list<PriceObservation> */
    public function observeMany(array $products, \DateTimeImmutable $at): array;
}
