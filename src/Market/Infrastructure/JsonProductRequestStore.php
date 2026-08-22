<?php

namespace App\Market\Infrastructure;

final readonly class JsonProductRequestStore
{
    public function __construct(private string $directory, private string $secret)
    {
    }

    public function save(string $product, string $email, string $ipAddress): void
    {
        $directory = $this->directory.'/requests';
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to save the product request.');
        }
        $record = [
            'timestamp' => gmdate(DATE_ATOM),
            'product' => trim($product),
            'email' => strtolower(trim($email)),
            'ip_hash' => substr(hash_hmac('sha256', $ipAddress, $this->secret), 0, 20),
        ];
        $file = $directory.'/product-requests-'.gmdate('Y-m').'.jsonl';
        if (@file_put_contents($file, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Unable to save the product request.');
        }
        @chmod($file, 0660);
    }
}
