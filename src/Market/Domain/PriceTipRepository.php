<?php

declare(strict_types=1);

namespace App\Market\Domain;

interface PriceTipRepository
{
    public function submit(string $productSlug, string $listingUrl, string $email, string $ipAddress): PriceTip;

    /** @return list<PriceTip> */
    public function all(): array;

    public function pruneExpired(?\DateTimeImmutable $now = null): int;
}
