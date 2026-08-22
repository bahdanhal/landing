<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure;

use App\Audit\Application\AuditLoggerInterface;

final class JsonlAuditLogger implements AuditLoggerInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly int $retentionDays = 14,
    ) {
    }

    public function newAuditId(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function log(string $event, array $payload = []): void
    {
        $directory = rtrim($this->directory, '/');
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            return;
        }

        $record = [
            'timestamp' => gmdate(DATE_ATOM),
            'event' => $event,
            'payload' => $payload,
        ];
        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $file = $directory . '/audit-' . gmdate('Y-m-d') . '.jsonl';

        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        $this->pruneOldLogs($directory);
    }

    public function safeUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return 'https://invalid.target';
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';

        return $scheme . '://' . $host . $port . ($path === '' ? '/' : $path);
    }

    private function pruneOldLogs(string $directory): void
    {
        if (random_int(1, 100) !== 1) {
            return;
        }

        $cutoff = time() - ($this->retentionDays * 86400);
        foreach (glob($directory . '/audit-*.jsonl') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
