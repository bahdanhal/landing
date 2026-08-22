<?php

declare(strict_types=1);

namespace App\Service;

final class AuditLogger
{
    public function __construct(
        private readonly string $directory,
        private readonly int $retentionDays,
    ) {
    }

    public function newAuditId(): string
    {
        return bin2hex(random_bytes(6));
    }

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

        if (random_int(1, 100) === 1) {
            $this->removeExpiredLogs();
        }
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

    private function logFile(): string
    {
        return rtrim($this->directory, '/') . '/audit-' . gmdate('Y-m-d') . '.jsonl';
    }

    private function removeExpiredLogs(): void
    {
        $cutoff = time() - max(1, $this->retentionDays) * 86400;
        foreach (glob(rtrim($this->directory, '/') . '/audit-*.jsonl') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
