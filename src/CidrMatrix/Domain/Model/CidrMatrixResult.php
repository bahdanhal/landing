<?php

declare(strict_types=1);

namespace App\CidrMatrix\Domain\Model;

final readonly class CidrMatrixResult
{
    /**
     * @param list<CidrBlock> $parsedBlocks
     * @param list<SubnetCollision> $collisions
     * @param list<array<string, string>> $containments
     * @param list<CidrDiagnostic> $diagnostics
     * @param list<array<string, mixed>> $matrixGrid
     * @param list<string> $warnings
     */
    public function __construct(
        public array $parsedBlocks,
        public bool $hasCollisions,
        public int $collisionCount,
        public array $collisions,
        public array $containments,
        public ?string $freeSubnetCidr,
        public array $matrixGrid,
        public array $diagnostics,
        public array $warnings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'has_collisions' => $this->hasCollisions,
            'collision_count' => $this->collisionCount,
            'collisions' => array_map(static fn (SubnetCollision $c) => $c->toArray(), $this->collisions),
            'containments' => $this->containments,
            'free_subnet_cidr' => $this->freeSubnetCidr,
            'parsed_blocks' => array_map(static fn (CidrBlock $b) => $b->toArray(), $this->parsedBlocks),
            'matrix_grid' => $this->matrixGrid,
            'diagnostics' => array_map(static fn (CidrDiagnostic $d) => $d->toArray(), $this->diagnostics),
            'warnings' => $this->warnings,
        ];
    }
}
