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

    /** @param list<string> $slugs @return list<PriceObservation> */
    public function observeMany(array $slugs, ?\DateTimeImmutable $at = null): array
    {
        $products = array_map(function (string $slug) {
            return $this->catalog->get($slug) ?? throw new \InvalidArgumentException('Unknown market product: '.$slug);
        }, $slugs);
        $observations = [];
        foreach (array_chunk($products, 8) as $batch) {
            foreach ($this->researcher->observeMany($batch, $at ?? new \DateTimeImmutable('now', new \DateTimeZone('Europe/Warsaw'))) as $observation) {
                $this->repository->save($observation);
                $observations[] = $observation;
            }
        }

        return $observations;
    }
}
