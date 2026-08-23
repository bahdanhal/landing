<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure;

final readonly class AuditLogger
{
    public function __construct(
        private string $directory,
        private int $retentionDays,
    ) {
    }

    public function newAuditId(): string
    {
        return bin2hex(random_bytes(6));
    }

    /** @param array<string, mixed> $context */
    public function log(string $event, array $context = []): void
    {
        $record = [
            'timestamp' => gmdate(DATE_ATOM),
            'event' => $event,
            ...$context,
        ];
        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;

        $directory = rtrim($this->directory, '/');
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            error_log(rtrim($line));
            return;
        }

        file_put_contents($this->logFile(), $line, FILE_APPEND | LOCK_EX);
        error_log(rtrim($line));
    }

    public function safeUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            return '[invalid-url]';
        }

        $safe = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $safe .= ':' . $parts['port'];
        }
        $safe .= $parts['path'] ?? '/';

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            $keys = array_values(array_filter(array_map('strval', array_keys($query))));
            sort($keys);
            if ($keys !== []) {
                $safe .= '?' . implode('&', $keys);
            }
        }

        return $safe;
    }

    public function safeError(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }

        return preg_replace_callback(
            '#https?://[^\s"\'<>]+#i',
            fn (array $matches): string => $this->safeUrl($matches[0]) ?? '[url]',
            $message,
        );
    }

    /** @return list<array<string, mixed>> */
    public function events(): array
    {
        $files = glob(rtrim($this->directory, '/') . '/audit-*.jsonl') ?: [];
        rsort($files);
        $events = [];

        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach (array_reverse($lines) as $line) {
                try {
                    $event = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                    if (is_array($event) && isset($event['timestamp'], $event['event'])) {
                        $events[] = $event;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        usort($events, static fn (array $left, array $right): int => $right['timestamp'] <=> $left['timestamp']);

        return $events;
    }

    private function logFile(): string
    {
        return rtrim($this->directory, '/') . '/audit-' . gmdate('Y-m-d') . '.jsonl';
    }

    public function pruneExpired(): int
    {
        $cutoff = time() - max(1, $this->retentionDays) * 86400;
        $deleted = 0;
        foreach (glob(rtrim($this->directory, '/') . '/audit-*.jsonl') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
