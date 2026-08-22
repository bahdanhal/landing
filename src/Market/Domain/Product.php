<?php

declare(strict_types=1);

namespace App\Market\Domain;

final readonly class Product
{
    /** @param array<string, string> $specifications */
    public function __construct(public string $slug, public string $name, public string $definition, public string $category, public array $specifications = [])
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new \InvalidArgumentException('Product slug must be URL-safe.');
        }
    }
}
