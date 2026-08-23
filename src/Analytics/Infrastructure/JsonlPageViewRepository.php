<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure;

use App\Analytics\Domain\PageView;
use App\Analytics\Domain\PageViewRepository;

/**
 * @deprecated Use App\Analytics\Infrastructure\DoctrinePageViewRepository for production runtime persistence.
 *             Retained for data migration and lightweight test fixtures.
 */
final readonly class JsonlPageViewRepository implements PageViewRepository
{
    public function __construct(
        private string $directory,
        private int $retentionDays,
    ) {
    }

    public function save(PageView $pageView): void
    {
        $this->ensureDirectory();
        $path = $this->directory . '/' . $pageView->occurredAt->format('Y-m-d') . '.jsonl';
        $line = json_encode([
            'occurred_at' => $pageView->occurredAt->format(DATE_ATOM),
            'visitor_hash' => $pageView->visitorHash,
            'path' => $pageView->path,
            'source' => $pageView->source,
            'referrer_host' => $pageView->referrerHost,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        $this->prune($pageView->occurredAt);
    }

    public function since(\DateTimeImmutable $since): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $views = [];
        foreach (glob($this->directory . '/*.jsonl') ?: [] as $path) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                try {
                    $data = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                    $view = new PageView(
                        new \DateTimeImmutable((string) $data['occurred_at']),
                        (string) $data['visitor_hash'],
                        (string) $data['path'],
                        (string) $data['source'],
                        isset($data['referrer_host']) ? (string) $data['referrer_host'] : null,
                    );
                    if ($view->occurredAt >= $since) {
                        $views[] = $view;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        usort($views, static fn (PageView $left, PageView $right): int => $left->occurredAt <=> $right->occurredAt);

        return $views;
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Unable to create analytics directory.');
        }
    }

    public function prune(\DateTimeImmutable $now): int
    {
        $cutoff = $now->modify(sprintf('-%d days', $this->retentionDays))->format('Y-m-d');
        $pruned = 0;
        foreach (glob($this->directory . '/*.jsonl') ?: [] as $path) {
            if (basename($path, '.jsonl') < $cutoff) {
                if (@unlink($path)) {
                    ++$pruned;
                }
            }
        }

        return $pruned;
    }
}
