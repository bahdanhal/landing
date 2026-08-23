<?php

declare(strict_types=1);

namespace App\Market\Domain;

final readonly class PriceTip
{
    public function __construct(
        public string $productSlug,
        public string $listingUrl,
        public string $email,
        public string $ipHash,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $expiresAt,
    ) {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $productSlug)) {
            throw new \InvalidArgumentException('Invalid product slug.');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Invalid email address.');
        }
        if ($expiresAt <= $submittedAt) {
            throw new \InvalidArgumentException('Price tip expiry must follow its submission time.');
        }
    }

    /** @return array{product_slug:string,listing_url:string,email:string,ip_hash:string,submitted_at:string,expires_at:string} */
    public function toArray(): array
    {
        return [
            'product_slug' => $this->productSlug,
            'listing_url' => $this->listingUrl,
            'email' => $this->email,
            'ip_hash' => $this->ipHash,
            'submitted_at' => $this->submittedAt->format(DATE_ATOM),
            'expires_at' => $this->expiresAt->format(DATE_ATOM),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['product_slug'],
            (string) $data['listing_url'],
            (string) ($data['email'] ?? ''),
            (string) $data['ip_hash'],
            new \DateTimeImmutable((string) $data['submitted_at']),
            new \DateTimeImmutable((string) $data['expires_at']),
        );
    }
}
