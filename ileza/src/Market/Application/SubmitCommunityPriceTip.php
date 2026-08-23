<?php

declare(strict_types=1);

namespace App\Market\Application;

use App\Market\Domain\PriceTip;
use App\Market\Domain\PriceTipRepository;

final readonly class SubmitCommunityPriceTip
{
    public function __construct(
        private ProductCatalog $catalog,
        private PriceTipRepository $priceTips,
    ) {
    }

    public function execute(string $slug, string $listingUrl, string $email, string $ipAddress): PriceTip
    {
        if ($this->catalog->get($slug) === null) {
            throw new \InvalidArgumentException(sprintf('Unknown product slug: %s', $slug));
        }

        return $this->priceTips->submit($slug, $listingUrl, $email, $ipAddress);
    }
}
