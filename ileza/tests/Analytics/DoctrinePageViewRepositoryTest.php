<?php

declare(strict_types=1);

namespace App\Tests\Analytics;

use App\Analytics\Domain\PageView;
use App\Analytics\Infrastructure\DoctrinePageViewRepository;
use App\Tests\DoctrineTestCase;

final class DoctrinePageViewRepositoryTest extends DoctrineTestCase
{
    public function testSavesAndRetrievesPageViewsSinceDate(): void
    {
        $repository = new DoctrinePageViewRepository($this->entityManager, 90);

        $now = new \DateTimeImmutable('2026-08-23T12:00:00+00:00');
        $twoDaysAgo = $now->modify('-2 days');
        $tenDaysAgo = $now->modify('-10 days');

        $view1 = new PageView(
            $tenDaysAgo,
            'visitor-hash-1',
            '/pl/audit',
            'direct',
            null
        );
        $view2 = new PageView(
            $twoDaysAgo,
            'visitor-hash-2',
            '/en/tools',
            'referral',
            'example.com'
        );
        $view3 = new PageView(
            $now,
            'visitor-hash-3',
            '/pl/tools/stawki-b2b',
            'direct',
            'google.com'
        );

        $repository->save($view1);
        $repository->save($view2);
        $repository->save($view3);

        $sinceFiveDaysAgo = $now->modify('-5 days');
        $recentViews = $repository->since($sinceFiveDaysAgo);

        self::assertCount(2, $recentViews);
        self::assertSame('visitor-hash-2', $recentViews[0]->visitorHash);
        self::assertSame('/en/tools', $recentViews[0]->path);
        self::assertSame('referral', $recentViews[0]->source);
        self::assertSame('example.com', $recentViews[0]->referrerHost);

        self::assertSame('visitor-hash-3', $recentViews[1]->visitorHash);
        self::assertSame('/pl/tools/stawki-b2b', $recentViews[1]->path);
        self::assertSame('direct', $recentViews[1]->source);
        self::assertSame('google.com', $recentViews[1]->referrerHost);
    }

    public function testPrunesOldRecords(): void
    {
        $repository = new DoctrinePageViewRepository($this->entityManager, 30);

        $now = new \DateTimeImmutable('2026-08-23T12:00:00+00:00');
        $oldView = new PageView(
            $now->modify('-45 days'),
            'old-visitor',
            '/',
            'direct',
            null
        );
        $recentView = new PageView(
            $now->modify('-10 days'),
            'recent-visitor',
            '/pl/audit',
            'direct',
            null
        );

        $repository->save($oldView);
        $repository->save($recentView);

        $deletedCount = $repository->prune($now);
        self::assertSame(1, $deletedCount);

        $allViews = $repository->since($now->modify('-100 days'));
        self::assertCount(1, $allViews);
        self::assertSame('recent-visitor', $allViews[0]->visitorHash);
    }
}
