<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\ProductRequestStore;

final readonly class RecordProductRequest
{
    public function __construct(
        private ProductRequestStore $productRequests,
    ) {
    }

    public function execute(string $product, string $email, string $ipAddress): void
    {
        $cleanProduct = trim($product);
        $cleanEmail = strtolower(trim($email));

        if ($cleanProduct === '' || mb_strlen($cleanProduct) > 160) {
            throw new \InvalidArgumentException('Product name must be between 1 and 160 characters.');
        }

        if ($cleanEmail !== '' && filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        $this->productRequests->save($cleanProduct, $cleanEmail, $ipAddress);
    }
}
