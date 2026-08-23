<?php

declare(strict_types=1);

namespace App\Market\Infrastructure;

use App\Market\Domain\ProductRequestStore;

/**
 * @deprecated Use App\Market\Infrastructure\DoctrineProductRequestStore for production runtime persistence.
 *             Retained for data migration and lightweight test fixtures.
 */
final readonly class JsonProductRequestStore implements ProductRequestStore
{
    public function __construct(
        private string $directory,
        private string $secret,
    ) {
    }

    public function save(string $product, string $email, string $ipAddress): void
    {
        $directory = rtrim($this->directory, '/') . '/requests';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to save the product request.');
        }
        $record = [
            'timestamp' => gmdate(DATE_ATOM),
            'product' => trim($product),
            'email' => strtolower(trim($email)),
            'ip_hash' => substr(hash_hmac('sha256', $ipAddress, $this->secret), 0, 20),
        ];
        $file = $directory . '/product-requests-' . gmdate('Y-m') . '.jsonl';
        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($file, $json . "\n", FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Unable to save the product request.');
        }
        @chmod($file, 0660);
    }

    /** @return list<array{timestamp: string, product: string, email: string, ip_hash: string}> */
    public function all(): array
    {
        $directory = rtrim($this->directory, '/') . '/requests';
        if (!is_dir($directory)) {
            return [];
        }
        $files = glob($directory . '/product-requests-*.jsonl') ?: [];
        rsort($files);
        $requests = [];
        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach (array_reverse($lines) as $line) {
                try {
                    $item = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                    if (is_array($item)) {
                        $requests[] = [
                            'timestamp' => (string) ($item['timestamp'] ?? ''),
                            'product' => (string) ($item['product'] ?? ''),
                            'email' => (string) ($item['email'] ?? ''),
                            'ip_hash' => (string) ($item['ip_hash'] ?? ''),
                        ];
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $requests;
    }
}
