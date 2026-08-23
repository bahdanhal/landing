<?php

declare(strict_types=1);

namespace App\CidrMatrix\Application;

use App\CidrMatrix\Domain\Engine\CidrCalculator;
use App\CidrMatrix\Domain\Model\CidrMatrixResult;

final readonly class CidrMatrixService
{
    private CidrCalculator $calculator;

    public function __construct(?CidrCalculator $calculator = null)
    {
        $this->calculator = $calculator ?? new CidrCalculator();
    }

    /**
     * @param list<string> $cidrs
     */
    public function analyze(
        array $cidrs,
        ?int $requestedFreePrefix = null,
        ?string $parentCidr = null,
    ): CidrMatrixResult {
        return $this->calculator->analyze($cidrs, $requestedFreePrefix, $parentCidr);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPresets(): array
    {
        $specPath = dirname(__DIR__, 3) . '/specs/cidr-matrix.spec.json';
        if (!file_exists($specPath)) {
            return [];
        }

        $content = file_get_contents($specPath);
        if ($content === false) {
            return [];
        }

        /** @var array{presets?: list<array<string, mixed>>} $spec */
        $spec = json_decode($content, true) ?? [];

        return $spec['presets'] ?? [];
    }
}
