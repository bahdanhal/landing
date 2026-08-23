<?php

declare(strict_types=1);

namespace App\Market\Infrastructure;

use App\Entity\PriceObservationEntity;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePriceObservationRepository implements PriceObservationRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(PriceObservation $observation): void
    {
        $repository = $this->entityManager->getRepository(PriceObservationEntity::class);

        /** @var PriceObservationEntity|null $existing */
        $existing = $repository->findOneBy([
            'productSlug' => $observation->productSlug,
            'observedAt' => $observation->observedAt,
        ]);

        if ($existing !== null) {
            $existing->updateFromObservation($observation);
        } else {
            $entity = new PriceObservationEntity(
                $observation->productSlug,
                $observation->observedAt,
                $observation->medianGrosz,
                $observation->lowGrosz,
                $observation->highGrosz,
                $observation->sampleSize,
                $observation->confidence,
                $observation->summary !== '' ? $observation->summary : null,
                $observation->methodology !== '' ? $observation->methodology : PriceObservation::METHODOLOGY_MANUAL,
            );

            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    /** @return list<PriceObservation> */
    public function history(string $productSlug): array
    {
        $repository = $this->entityManager->getRepository(PriceObservationEntity::class);
        /** @var list<PriceObservationEntity> $entities */
        $entities = $repository->findBy(
            ['productSlug' => $productSlug],
            ['observedAt' => 'DESC']
        );

        return array_map(
            static fn (PriceObservationEntity $entity): PriceObservation => new PriceObservation(
                $entity->getProductSlug(),
                $entity->getObservedAt(),
                $entity->getMedianGrosz(),
                $entity->getLowGrosz(),
                $entity->getHighGrosz(),
                $entity->getSampleSize(),
                $entity->getConfidence(),
                $entity->getSummary() ?? '',
                $entity->getMethodology(),
            ),
            $entities
        );
    }

    public function latest(string $productSlug): ?PriceObservation
    {
        $history = $this->history($productSlug);

        return $history[0] ?? null;
    }

    public function delete(string $productSlug, string $date): void
    {
        $repository = $this->entityManager->getRepository(PriceObservationEntity::class);
        /** @var list<PriceObservationEntity> $entities */
        $entities = $repository->findBy(['productSlug' => $productSlug]);

        foreach ($entities as $entity) {
            if ($entity->getObservedAt()->format('Y-m-d') === $date) {
                $this->entityManager->remove($entity);
            }
        }

        $this->entityManager->flush();
    }
}
