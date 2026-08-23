<?php

declare(strict_types=1);

namespace App\Analytics\Domain;

final readonly class PageView
{
    public function __construct(
        public \DateTimeImmutable $occurredAt,
        public string $visitorHash,
        public string $path,
        public string $source,
        public ?string $referrerHost,
    ) {
    }
}
