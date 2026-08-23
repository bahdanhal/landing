<?php

declare(strict_types=1);

namespace App\Lead\Infrastructure;

use App\Lead\Domain\Lead;
use App\Lead\Domain\LeadRepository;

/**
 * @deprecated Use App\Lead\Infrastructure\DoctrineLeadRepository for production runtime persistence.
 *             Retained for data migration and lightweight test fixtures.
 */
final readonly class JsonlLeadRepository implements LeadRepository
{
    public function __construct(private string $directory)
    {
    }

    public function save(Lead $lead): void
    {
        $directory = rtrim($this->directory, '/');
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to save contact request: storage directory is unavailable.');
        }

        $record = $lead->toArray();
        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $file = $directory . '/leads-' . gmdate('Y-m') . '.jsonl';

        if (file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Unable to save contact request.');
        }
        @chmod($file, 0660);
    }

    public function all(): array
    {
        $files = glob(rtrim($this->directory, '/') . '/leads-*.jsonl') ?: [];
        rsort($files);
        $leads = [];

        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach (array_reverse($lines) as $line) {
                try {
                    $data = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                    if (is_array($data)) {
                        $leads[] = Lead::fromArray($data);
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        usort($leads, static fn (Lead $left, Lead $right): int => $right->createdAt <=> $left->createdAt);

        return $leads;
    }
}
