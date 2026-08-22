<?php

namespace App\Market\Application;

use App\Market\Domain\PriceObservation;
use App\Market\Domain\Product;

interface MarketResearcher
{
    public function observe(Product $product, \DateTimeImmutable $at): PriceObservation;
}
