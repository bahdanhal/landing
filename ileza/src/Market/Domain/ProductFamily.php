<?php

declare(strict_types=1);

namespace App\Market\Domain;

final readonly class ProductFamily
{
    /** @param list<Product> $configurations */
    public function __construct(
        public string $slug,
        public string $name,
        public string $category,
        public string $image,
        public string $imageCredit,
        public string $imageSource,
        public array $configurations,
    ) {
        if ($configurations === []) {
            throw new \InvalidArgumentException('A product family needs at least one configuration.');
        }
    }

    public function defaultConfiguration(): Product
    {
        return $this->configurations[0];
    }
}
