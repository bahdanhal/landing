<?php

declare(strict_types=1);

namespace App\Shared\Domain;

final readonly class HashedIp
{
    private string $hash;

    public function __construct(string $ip, string $secret)
    {
        $normalizedIp = trim($ip);
        if ($normalizedIp === '') {
            $this->hash = hash_hmac('sha256', '0.0.0.0', $secret);
        } else {
            $this->hash = hash_hmac('sha256', $normalizedIp, $secret);
        }
    }

    public static function fromHash(string $hash): self
    {
        $instance = new \ReflectionClass(self::class)->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(self::class, 'hash');
        $property->setAccessible(true);
        $property->setValue($instance, $hash);
        return $instance;
    }

    public function toString(): string
    {
        return $this->hash;
    }

    public function __toString(): string
    {
        return $this->hash;
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->hash, $other->hash);
    }
}
