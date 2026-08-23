<?php

declare(strict_types=1);

namespace App\CaddyTranspiler\Domain\Model;

final readonly class TranspileResult
{
    /**
     * @param list<MigrationAdvisory> $advisories
     * @param list<string> $detectedFeatures
     * @param list<string> $omittedDirectives
     */
    public function __construct(
        public string $caddyfile,
        public ServerType $sourceType,
        public array $advisories = [],
        public array $detectedFeatures = [],
        public array $omittedDirectives = [],
        public ?string $siteAddress = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'caddyfile' => $this->caddyfile,
            'source_type' => $this->sourceType->value,
            'site_address' => $this->siteAddress,
            'detected_features' => $this->detectedFeatures,
            'omitted_directives' => $this->omittedDirectives,
            'advisories' => array_map(static fn (MigrationAdvisory $a): array => $a->toArray(), $this->advisories),
        ];
    }
}
