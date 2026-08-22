<?php

namespace App\Market\Application;

use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;

final readonly class ObserveMarket
{
    public function __construct(private ProductCatalog $catalog, private MarketResearcher $researcher, private PriceObservationRepository $repository)
    {
    }

    public function observe(string $slug, ?\DateTimeImmutable $at = null): PriceObservation
    {
        $product = $this->catalog->get($slug) ?? throw new \InvalidArgumentException('Unknown market product: '.$slug);
        $observation = $this->researcher->observe($product, $at ?? new \DateTimeImmutable('now', new \DateTimeZone('Europe/Warsaw')));
        $this->repository->save($observation);

        return $observation;
    }
}
