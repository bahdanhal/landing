<?php

declare(strict_types=1);

namespace App\Lead\Infrastructure;

use App\Entity\LeadEntity;
use App\Lead\Domain\Lead;
use App\Lead\Domain\LeadRepository;
use Bahdan\LeadCaptureBundle\Domain\Lead as BaseLead;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLeadRepository implements LeadRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(BaseLead $lead): void
    {
        $entity = new LeadEntity(
            $lead->email,
            $lead->phone,
            $lead->message,
            $lead->ipHash,
            $lead->source,
            $lead->createdAt,
        );

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /** @return list<Lead> */
    public function all(): array
    {
        $repository = $this->entityManager->getRepository(LeadEntity::class);
        /** @var list<LeadEntity> $entities */
        $entities = $repository->findBy([], ['createdAt' => 'DESC']);

        return array_map(
            static fn (LeadEntity $entity): Lead => new Lead(
                $entity->getEmail(),
                $entity->getPhone(),
                $entity->getMessage(),
                $entity->getIpHash(),
                $entity->getSource(),
                $entity->getCreatedAt(),
            ),
            $entities
        );
    }
}
