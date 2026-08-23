<?php

declare(strict_types=1);

namespace App\Market\Domain;

interface ProductRequestStore
{
    public function save(string $product, string $email, string $ipAddress): void;

    /** @return list<array{timestamp: string, product: string, email: string, ip_hash: string}> */
    public function all(): array;
}
