<?php

declare(strict_types=1);

namespace App\CidrMatrix\Domain\Model;

final readonly class CidrDiagnostic
{
    public function __construct(
        public string $code,
        public string $severity,
        public string $title,
        public string $description,
        public ?string $context = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'code' => $this->code,
            'severity' => $this->severity,
            'title' => $this->title,
            'description' => $this->description,
        ];

        if ($this->context !== null) {
            $data['context'] = $this->context;
        }

        return $data;
    }
}
