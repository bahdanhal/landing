<?php

declare(strict_types=1);

namespace App\Lead\Domain;

final readonly class Lead
{
    public function __construct(
        public string $email,
        public string $phone,
        public string $message,
        public string $ipHash,
        public string $source,
        public \DateTimeImmutable $createdAt,
    ) {
        if ($email === '' && $phone === '') {
            throw new \InvalidArgumentException('An email address or phone number is required.');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Invalid email address provided for lead.');
        }
        if ($phone !== '' && !preg_match('/^\+?[0-9 ()-]{7,30}$/', $phone)) {
            throw new \InvalidArgumentException('Invalid phone number provided for lead.');
        }
        if (mb_strlen($message) > 1000) {
            throw new \InvalidArgumentException('Contact message is too long.');
        }
    }

    public static function create(string $email, string $phone, string $message, string $ipHash, string $source): self
    {
        $cleanEmail = strtolower(trim($email));
        $cleanSource = preg_replace('/[^a-z0-9_-]/i', '', $source) ?: 'website';

        return new self(
            $cleanEmail,
            trim($phone),
            trim(strip_tags($message)),
            $ipHash,
            $cleanSource,
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['email'] ?? ''),
            (string) ($data['phone'] ?? ''),
            (string) ($data['message'] ?? ''),
            (string) ($data['ip_hash'] ?? ''),
            (string) ($data['source'] ?? 'website'),
            new \DateTimeImmutable((string) ($data['timestamp'] ?? 'now')),
        );
    }

    /** @return array{timestamp: string, email: string, phone: string, message: string, ip_hash: string, source: string} */
    public function toArray(): array
    {
        return [
            'timestamp' => $this->createdAt->format(DATE_ATOM),
            'email' => $this->email,
            'phone' => $this->phone,
            'message' => $this->message,
            'ip_hash' => $this->ipHash,
            'source' => $this->source,
        ];
    }
}
