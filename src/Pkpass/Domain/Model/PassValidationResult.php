<?php

declare(strict_types=1);

namespace App\Pkpass\Domain\Model;

final readonly class PassValidationResult
{
    /**
     * @param list<ValidationFinding> $findings
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public bool $isValid,
        public array $findings,
        public ?PassType $passType = null,
        public ?string $organizationName = null,
        public ?string $passTypeIdentifier = null,
        public ?string $teamIdentifier = null,
        public ?string $serialNumber = null,
        public ?string $description = null,
        public array $metadata = [],
    ) {
    }

    public function errorCount(): int
    {
        return count(array_filter(
            $this->findings,
            static fn (ValidationFinding $f): bool => $f->severity === ValidationSeverity::Error
        ));
    }

    public function warningCount(): int
    {
        return count(array_filter(
            $this->findings,
            static fn (ValidationFinding $f): bool => $f->severity === ValidationSeverity::Warning
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'pass_type' => $this->passType?->value,
            'organization_name' => $this->organizationName,
            'pass_type_identifier' => $this->passTypeIdentifier,
            'team_identifier' => $this->teamIdentifier,
            'serial_number' => $this->serialNumber,
            'description' => $this->description,
            'error_count' => $this->errorCount(),
            'warning_count' => $this->warningCount(),
            'findings' => array_map(
                static fn (ValidationFinding $f): array => $f->toArray(),
                $this->findings
            ),
            'metadata' => $this->metadata,
        ];
    }
}
