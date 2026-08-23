<?php

declare(strict_types=1);

namespace App\Shared\Domain;

final readonly class Grosz
{
    public function __construct(public int $amount)
    {
    }

    public static function fromGrosz(int $amount): self
    {
        return new self($amount);
    }

    public static function fromZloty(float|int|string $pln): self
    {
        $floatVal = (float) $pln;
        return new self((int) round($floatVal * 100));
    }

    public function toPln(): float
    {
        return $this->amount / 100;
    }

    public function toFormattedPln(string $locale = 'pl'): string
    {
        $pln = $this->toPln();
        if ($locale === 'pl') {
            return number_format($pln, 2, ',', ' ') . ' zł';
        }

        return 'PLN ' . number_format($pln, 2, '.', ',');
    }

    public function add(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function subtract(self $other): self
    {
        return new self($this->amount - $other->amount);
    }

    public function multiply(float|int $factor): self
    {
        return new self((int) round($this->amount * $factor));
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->amount > $other->amount;
    }

    public function isLessThan(self $other): bool
    {
        return $this->amount < $other->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }
}
