<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Income\Domain\PolishIncomeCalculator;
use App\Mcp\IncomeCalculatorTools;
use PHPUnit\Framework\TestCase;

final class IncomeCalculatorToolsTest extends TestCase
{
    public function testIncomeCalculatorMcpToolOutput(): void
    {
        $calculator = new PolishIncomeCalculator();
        $tool = new IncomeCalculatorTools($calculator);

        $jsonOutput = $tool->calculateIncome(15000.0, 'linear', 'standard', 12.0, 500.0, false);
        $data = json_decode($jsonOutput, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(15000.0, (float) $data['budget_pln']);
        self::assertSame(2026, $data['assumptions_year']);
        self::assertSame('PLN', $data['currency']);
        self::assertArrayHasKey('comparison', $data);
        self::assertArrayHasKey('b2b', $data['comparison']);
    }
}
