<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Income\Domain\PolishIncomeCalculator;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class IncomeCalculatorTools
{
    public function __construct(private PolishIncomeCalculator $calculator)
    {
    }

    #[McpTool(
        name: 'calculate_polish_income_comparison',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Calculate Polish contract and tax net income comparison for a company budget across UoP (employment), Umowa Zlecenie (mandate), Umowa o Dzieło (work contract), and B2B (progressive, linear 19%, lump-sum/ryczałt).'
    )]
    public function calculateIncome(
        #[Schema(description: 'Total monthly company employer budget in PLN (e.g. 15000).')]
        float $budget,
        #[Schema(description: 'B2B taxation type: progressive, linear, or lump.')]
        string $taxation = 'progressive',
        #[Schema(description: 'B2B ZUS type: standard, preferential, or start.')]
        string $zus = 'standard',
        #[Schema(description: 'B2B lump sum tax rate percentage if taxation is lump (e.g. 12, 15, 8.5).')]
        float $lumpRate = 12.0,
        #[Schema(description: 'Monthly business operating costs in PLN for B2B.')]
        float $costs = 0.0,
        #[Schema(description: 'Whether the mandate contractor is a student under 26 (exempt from ZUS and PIT up to limit).')]
        bool $studentUnder26 = false,
    ): string {
        $result = $this->calculator->compare([
            'budget' => $budget,
            'taxation' => $taxation,
            'zus' => $zus,
            'lumpRate' => $lumpRate,
            'costs' => $costs,
            'studentUnder26' => $studentUnder26,
        ]);

        return $this->json([
            'budget_pln' => $budget,
            'assumptions_year' => 2026,
            'currency' => 'PLN',
            'comparison' => $result,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
