<?php

declare(strict_types=1);

namespace App\CaddyTranspiler\Domain\Model;

final readonly class MigrationAdvisory
{
    public function __construct(
        public string $code,
        public string $severity,
        public string $title,
        public string $description,
        public ?string $suggestion = null,
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity,
            'title' => $this->title,
            'description' => $this->description,
            'suggestion' => $this->suggestion,
        ];
    }
}
