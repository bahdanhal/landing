<?php

declare(strict_types=1);

namespace App\Market\Infrastructure;

use App\Entity\PriceTipEntity;
use App\Market\Domain\PriceTip;
use App\Market\Domain\PriceTipRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePriceTipRepository implements PriceTipRepository
{
    private const RETENTION_DAYS = 90;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $secret,
    ) {
    }

    public function submit(string $productSlug, string $listingUrl, string $email, string $ipAddress): PriceTip
    {
        $submittedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiresAt = $submittedAt->modify(sprintf('+%d days', self::RETENTION_DAYS));
        $normalizedUrl = $this->normalizeUrl($listingUrl);
        $cleanEmail = strtolower(trim($email));
        $ipHash = substr(hash_hmac('sha256', $ipAddress, $this->secret), 0, 20);

        $entity = new PriceTipEntity(
            $productSlug,
            $normalizedUrl,
            $cleanEmail,
            $ipHash,
            $submittedAt,
            $expiresAt
        );

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return new PriceTip(
            $productSlug,
            $normalizedUrl,
            $cleanEmail,
            $ipHash,
            $submittedAt,
            $expiresAt
        );
    }

    /** @return list<PriceTip> */
    public function all(): array
    {
        $repository = $this->entityManager->getRepository(PriceTipEntity::class);
        /** @var list<PriceTipEntity> $entities */
        $entities = $repository->findBy([], ['submittedAt' => 'DESC']);

        return array_map(
            static fn (PriceTipEntity $entity): PriceTip => new PriceTip(
                $entity->getProductSlug(),
                $entity->getListingUrl(),
                $entity->getEmail(),
                $entity->getIpHash(),
                $entity->getSubmittedAt(),
                $entity->getExpiresAt(),
            ),
            $entities
        );
    }

    public function pruneExpired(?\DateTimeImmutable $now = null): int
    {
        $reference = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return (int) $this->entityManager->createQuery(
            'DELETE FROM App\Entity\PriceTipEntity t WHERE t.expiresAt <= :now'
        )->setParameter('now', $reference)->execute();
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048) {
            throw new \InvalidArgumentException('Enter a valid public listing URL.');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('Enter a valid public listing URL.');
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('Only public HTTP and HTTPS listing URLs are accepted.');
        }
        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if ($host === '' || $host === 'localhost' || !str_contains($host, '.')) {
            throw new \InvalidArgumentException('Enter a public marketplace URL.');
        }
        if (
            filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
        ) {
            throw new \InvalidArgumentException('Private and reserved network URLs are not accepted.');
        }
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = $parts['path'] ?? '/';

        return sprintf('%s://%s%s%s', $scheme, $host, $port, $path === '' ? '/' : $path);
    }
}
