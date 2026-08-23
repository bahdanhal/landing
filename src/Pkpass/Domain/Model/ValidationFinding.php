<?php

declare(strict_types=1);

namespace App\Pkpass\Domain\Model;

final readonly class ValidationFinding
{
    public function __construct(
        public string $code,
        public ValidationSeverity $severity,
        public string $title,
        public string $description,
        public ?string $field = null,
        public ?string $file = null,
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity->value,
            'title' => $this->title,
            'description' => $this->description,
            'field' => $this->field,
            'file' => $this->file,
        ];
    }
}
