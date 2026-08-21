<?php

namespace App\Service;

final class ContactLeadStore
{
    public function __construct(
        private readonly string $directory,
        private readonly string $secret,
    ) {
    }

    public function store(string $email, string $ipAddress, string $source): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Unable to save your contact request right now.');
        }

        $record = [
            'timestamp' => gmdate(DATE_ATOM),
            'email' => strtolower(trim($email)),
            'ip_hash' => substr(hash_hmac('sha256', $ipAddress, $this->secret), 0, 20),
            'source' => preg_replace('/[^a-z0-9_-]/i', '', $source) ?: 'website',
        ];
        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $file = $this->directory.'/leads-'.gmdate('Y-m').'.jsonl';

        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Unable to save your contact request right now.');
        }
        @chmod($file, 0660);
    }
}
