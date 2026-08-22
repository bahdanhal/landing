<?php

declare(strict_types=1);

namespace App\Lead\Domain;

final readonly class Lead
{
    public function __construct(
        public string $email,
        public string $ipHash,
        public string $source,
        public \DateTimeImmutable $createdAt,
    ) {
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address provided for lead.');
        }
    }

    public static function create(string $email, string $ipHash, string $source): self
    {
        $cleanEmail = strtolower(trim($email));
        $cleanSource = preg_replace('/[^a-z0-9_-]/i', '', $source) ?: 'website';

        return new self($cleanEmail, $ipHash, $cleanSource, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    public function toArray(): array
    {
        return [
            'timestamp' => $this->createdAt->format(DATE_ATOM),
            'email' => $this->email,
            'ip_hash' => $this->ipHash,
            'source' => $this->source,
        ];
    }
}
