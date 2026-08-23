<?php

declare(strict_types=1);

namespace App\Market\Infrastructure;

use App\Entity\ProductRequestEntity;
use App\Market\Domain\ProductRequestStore;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineProductRequestStore implements ProductRequestStore
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $secret,
    ) {
    }

    public function save(string $product, string $email, string $ipAddress): void
    {
        $ipHash = substr(hash_hmac('sha256', $ipAddress, $this->secret), 0, 20);
        $createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $entity = new ProductRequestEntity(
            trim($product),
            strtolower(trim($email)),
            $ipHash,
            $createdAt
        );

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /** @return list<array{timestamp: string, product: string, email: string, ip_hash: string}> */
    public function all(): array
    {
        $repository = $this->entityManager->getRepository(ProductRequestEntity::class);
        /** @var list<ProductRequestEntity> $entities */
        $entities = $repository->findBy([], ['createdAt' => 'DESC']);

        return array_map(
            static fn (ProductRequestEntity $entity): array => [
                'timestamp' => $entity->getCreatedAt()->format(DATE_ATOM),
                'product' => $entity->getProduct(),
                'email' => $entity->getEmail(),
                'ip_hash' => $entity->getIpHash(),
            ],
            $entities
        );
    }
}
