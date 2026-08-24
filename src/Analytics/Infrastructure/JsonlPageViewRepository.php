<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure;

use App\Analytics\Domain\PageView;
use App\Analytics\Domain\PageViewRepository;
use Bahdan\PrivacyAnalyticsBundle\Domain\PageView as BasePageView;
use Bahdan\PrivacyAnalyticsBundle\Infrastructure\JsonlPageViewRepository as BaseJsonlPageViewRepository;

final readonly class JsonlPageViewRepository implements PageViewRepository
{
    private BaseJsonlPageViewRepository $inner;

    public function __construct(
        string $directory,
        int $retentionDays = 90,
    ) {
        $this->inner = new BaseJsonlPageViewRepository($directory, $retentionDays);
    }

    public function save(BasePageView $pageView): void
    {
        $this->inner->save($pageView);
    }

    /** @return list<PageView> */
    public function since(\DateTimeImmutable $since): array
    {
        $views = $this->inner->since($since);
        return array_map(
            static fn ($view): PageView => new PageView(
                $view->occurredAt,
                $view->visitorHash,
                $view->path,
                $view->source,
                $view->referrerHost,
            ),
            $views
        );
    }

    public function prune(\DateTimeImmutable $now): int
    {
        return $this->inner->prune($now);
    }
}
