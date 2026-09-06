<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure;

use App\Analytics\Domain\PageView;
use App\Analytics\Domain\PageViewRepository;
use App\Entity\PageViewEntity;
use Bahdan\PrivacyAnalyticsBundle\Domain\PageView as BasePageView;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePageViewRepository implements PageViewRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private int $retentionDays = 90,
    ) {
    }

    public function save(BasePageView $pageView): void
    {
        $this->entityManager->getConnection()->insert('page_views', [
            'occurred_at' => $pageView->occurredAt->format('Y-m-d H:i:s'),
            'visitor_hash' => $pageView->visitorHash,
            'path' => $pageView->path,
            'source' => $pageView->source,
            'referrer_host' => $pageView->referrerHost,
        ]);
    }

    /** @return list<PageView> */
    public function since(\DateTimeImmutable $since): array
    {
        $repository = $this->entityManager->getRepository(PageViewEntity::class);
        $qb = $repository->createQueryBuilder('p');
        $qb->where('p.occurredAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('p.occurredAt', 'ASC');

        /** @var list<PageViewEntity> $entities */
        $entities = $qb->getQuery()->getResult();

        return array_map(
            static fn (PageViewEntity $entity): PageView => new PageView(
                $entity->getOccurredAt(),
                $entity->getVisitorHash(),
                $entity->getPath(),
                $entity->getSource(),
                $entity->getReferrerHost()
            ),
            $entities
        );
    }

    /**
     * @return array{
     *     privacy: string,
     *     last_7_days: array{
     *         page_views: int,
     *         unique_visitors: int,
     *         sources: array<string, int>,
     *         referring_domains: array<string, int>,
     *         top_paths: array<string, int>
     *     },
     *     last_30_days: array{
     *         page_views: int,
     *         unique_visitors: int,
     *         sources: array<string, int>,
     *         referring_domains: array<string, int>,
     *         top_paths: array<string, int>
     *     },
     *     daily: list<array{date: string, page_views: int, unique_visitors: int, top_paths: array<string, int>}>,
     *     weekly: list<array{week: string, start_date: string, end_date: string, page_views: int, unique_visitors: int, top_paths: array<string, int>}>
     * }
     */
    public function summary(\DateTimeImmutable $now): array
    {
        $thirtyDaysAgo = $now->modify('-30 days');
        $sevenDaysAgo = $now->modify('-7 days');
        $aggregates = $this->aggregateDailyAndWeekly($now, $thirtyDaysAgo);

        return [
            'privacy' => 'Cookie-free aggregates. IP addresses, query strings and full referrers are never stored.',
            'last_7_days' => $this->aggregatePeriod($sevenDaysAgo),
            'last_30_days' => $this->aggregatePeriod($thirtyDaysAgo),
            'daily' => $aggregates['daily'],
            'weekly' => $aggregates['weekly'],
        ];
    }

    /**
     * @return array{page_views: int, unique_visitors: int, sources: array<string, int>, referring_domains: array<string, int>, top_paths: array<string, int>}
     */
    private function aggregatePeriod(\DateTimeImmutable $since): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        /** @var array{total_views?: mixed, unique_visitors?: mixed} $counts */
        $counts = $qb->select('COUNT(p.id) as total_views', 'COUNT(DISTINCT p.visitorHash) as unique_visitors')
            ->from(PageViewEntity::class, 'p')
            ->where('p.occurredAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleResult();

        $pageViews = (int) ($counts['total_views'] ?? 0);
        $uniqueVisitors = (int) ($counts['unique_visitors'] ?? 0);

        /** @var list<array{source: string, cnt: mixed}> $sourcesResult */
        $sourcesResult = $this->entityManager->createQueryBuilder()
            ->select('p.source', 'COUNT(p.id) as cnt')
            ->from(PageViewEntity::class, 'p')
            ->where('p.occurredAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('p.source')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $sources = [];
        foreach ($sourcesResult as $row) {
            $src = trim((string) $row['source']);
            if ($src !== '') {
                $sources[$src] = (int) $row['cnt'];
            }
        }

        /** @var list<array{referrerHost: ?string, cnt: mixed}> $referrersResult */
        $referrersResult = $this->entityManager->createQueryBuilder()
            ->select('p.referrerHost', 'COUNT(p.id) as cnt')
            ->from(PageViewEntity::class, 'p')
            ->where('p.occurredAt >= :since')
            ->andWhere('p.referrerHost IS NOT NULL')
            ->andWhere("p.referrerHost != ''")
            ->setParameter('since', $since)
            ->groupBy('p.referrerHost')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $referringDomains = [];
        foreach ($referrersResult as $row) {
            $ref = trim((string) ($row['referrerHost'] ?? ''));
            if ($ref !== '') {
                $referringDomains[$ref] = (int) $row['cnt'];
            }
        }

        /** @var list<array{path: string, cnt: mixed}> $pathsResult */
        $pathsResult = $this->entityManager->createQueryBuilder()
            ->select('p.path', 'COUNT(p.id) as cnt')
            ->from(PageViewEntity::class, 'p')
            ->where('p.occurredAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('p.path')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $topPaths = [];
        foreach ($pathsResult as $row) {
            $pth = trim((string) $row['path']);
            if ($pth !== '') {
                $topPaths[$pth] = (int) $row['cnt'];
            }
        }

        return [
            'page_views' => $pageViews,
            'unique_visitors' => $uniqueVisitors,
            'sources' => $sources,
            'referring_domains' => $referringDomains,
            'top_paths' => $topPaths,
        ];
    }

    /**
     * @return array{
     *     daily: list<array{date: string, page_views: int, unique_visitors: int, top_paths: array<string, int>}>,
     *     weekly: list<array{week: string, start_date: string, end_date: string, page_views: int, unique_visitors: int, top_paths: array<string, int>}>
     * }
     */
    private function aggregateDailyAndWeekly(\DateTimeImmutable $now, \DateTimeImmutable $thirtyDaysAgo): array
    {
        /** @var array<string, array{page_views: int, visitors: array<string, bool>, paths: array<string, int>}> $days */
        $days = [];
        for ($offset = 29; $offset >= 0; --$offset) {
            $date = $now->modify(sprintf('-%d days', $offset))->format('Y-m-d');
            $days[$date] = ['page_views' => 0, 'visitors' => [], 'paths' => []];
        }

        /** @var array<string, array{week: string, start_date: string, end_date: string, views: int, visitors: array<string, bool>, paths: array<string, int>}> $weeks */
        $weeks = [];
        $earliestDay = $now->modify('-29 days');
        $cursor = $earliestDay->modify('Monday this week');
        $endSunday = $now->modify('Sunday this week');
        while ($cursor <= $endSunday) {
            $weekKey = $cursor->format('o-\WW');
            $weeks[$weekKey] = [
                'week' => $weekKey,
                'start_date' => $cursor->format('Y-m-d'),
                'end_date' => $cursor->modify('+6 days')->format('Y-m-d'),
                'views' => 0,
                'visitors' => [],
                'paths' => [],
            ];
            $cursor = $cursor->modify('+7 days');
        }

        /** @var list<array{occurredAt: mixed, visitorHash: mixed, path: mixed}> $records */
        $records = $this->entityManager->createQueryBuilder()
            ->select('p.occurredAt AS occurredAt', 'p.visitorHash AS visitorHash', 'p.path AS path')
            ->from(PageViewEntity::class, 'p')
            ->where('p.occurredAt >= :since')
            ->setParameter('since', $thirtyDaysAgo)
            ->getQuery()
            ->getScalarResult();

        foreach ($records as $record) {
            $rawDate = $record['occurredAt'];
            $dt = null;
            if ($rawDate instanceof \DateTimeInterface) {
                $dt = \DateTimeImmutable::createFromInterface($rawDate);
            } elseif (is_string($rawDate) && $rawDate !== '') {
                try {
                    $dt = new \DateTimeImmutable($rawDate);
                } catch (\Throwable) {
                    $dt = null;
                }
            }
            if ($dt === null) {
                continue;
            }

            $date = $dt->format('Y-m-d');
            $weekKey = $dt->format('o-\WW');
            $visitor = (string) $record['visitorHash'];
            $path = trim((string) ($record['path'] ?? ''));

            if (isset($days[$date])) {
                ++$days[$date]['page_views'];
                $days[$date]['visitors'][$visitor] = true;
                if ($path !== '') {
                    $days[$date]['paths'][$path] = ($days[$date]['paths'][$path] ?? 0) + 1;
                }
            }

            if (isset($weeks[$weekKey])) {
                ++$weeks[$weekKey]['views'];
                $weeks[$weekKey]['visitors'][$visitor] = true;
                if ($path !== '') {
                    $weeks[$weekKey]['paths'][$path] = ($weeks[$weekKey]['paths'][$path] ?? 0) + 1;
                }
            }
        }

        $sortTop = static function (array $paths): array {
            arsort($paths);

            return array_slice($paths, 0, 10, true);
        };

        $daily = [];
        foreach ($days as $date => $data) {
            $daily[] = [
                'date' => $date,
                'page_views' => $data['page_views'],
                'unique_visitors' => count($data['visitors']),
                'top_paths' => $sortTop($data['paths']),
            ];
        }

        $weekly = [];
        foreach ($weeks as $w) {
            $weekly[] = [
                'week' => $w['week'],
                'start_date' => $w['start_date'],
                'end_date' => $w['end_date'],
                'page_views' => $w['views'],
                'unique_visitors' => count($w['visitors']),
                'top_paths' => $sortTop($w['paths']),
            ];
        }

        return [
            'daily' => $daily,
            'weekly' => $weekly,
        ];
    }

    public function prune(\DateTimeImmutable $now): int
    {
        $cutoff = $now->modify(sprintf('-%d days', max(1, $this->retentionDays)));

        return (int) $this->entityManager->createQueryBuilder()
            ->delete(PageViewEntity::class, 'p')
            ->where('p.occurredAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
