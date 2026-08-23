<?php

declare(strict_types=1);

namespace App\Mcp;

use App\CidrMatrix\Application\CidrMatrixService;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class CidrTools
{
    public function __construct(private CidrMatrixService $service)
    {
    }

    /**
     * @param array<mixed> $cidrs
     */
    #[McpTool(
        name: 'calculate_cidr_overlap',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Analyze IPv4/IPv6 CIDR subnets for range collisions, full containment, 2D bit-tree matrix partition, and available free subnet allocation.'
    )]
    public function calculateCidrOverlap(
        #[Schema(description: 'Array of IPv4 or IPv6 CIDR strings to analyze for collisions and tree partition.')]
        array $cidrs,
        #[Schema(description: 'Optional target prefix length (e.g. 20, 24) to find available free subnet block inside parent range.')]
        ?int $requested_free_prefix = null,
        #[Schema(description: 'Optional parent CIDR string (e.g. 10.0.0.0/16) to constrain free subnet allocation search.')]
        ?string $parent_cidr = null,
    ): string {
        try {
            /** @var list<string> $stringCidrs */
            $stringCidrs = array_map(static fn ($c) => (string) $c, $cidrs);
            $result = $this->service->analyze($stringCidrs, $requested_free_prefix, $parent_cidr);

            return $this->json([
                'status' => 'completed',
                'result' => $result->toArray(),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
