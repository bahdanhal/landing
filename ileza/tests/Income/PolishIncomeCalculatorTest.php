<?php

declare(strict_types=1);

namespace App\Tests\Income;

use App\Income\Domain\PolishIncomeCalculator;
use PHPUnit\Framework\TestCase;

final class PolishIncomeCalculatorTest extends TestCase
{
    public function testIncomeComparisonMatchesParity(): void
    {
        $calculator = new PolishIncomeCalculator();
        $comparison = $calculator->compare([
            'budget' => 15000,
            'taxation' => 'linear',
            'zus' => 'standard',
            'costs' => 1000,
        ]);

        self::assertArrayHasKey('employment', $comparison);
        self::assertArrayHasKey('mandate', $comparison);
        self::assertArrayHasKey('work', $comparison);
        self::assertArrayHasKey('b2b', $comparison);

        self::assertSame(15000.0, $comparison['employment']['cost']);
        self::assertGreaterThan(0, $comparison['employment']['net']);
        self::assertGreaterThan(0, $comparison['b2b']['net']);
        self::assertSame(1000.0, $comparison['b2b']['businessCosts']);
    }

    public function testProgressiveTaxBrackets(): void
    {
        $calculator = new PolishIncomeCalculator();

        // Under 30,000 PLN tax-free allowance
        self::assertSame(0.0, $calculator->progressiveAnnualTax(25000));

        // In 12% bracket (e.g. 50,000 PLN)
        self::assertSame(2400.0, $calculator->progressiveAnnualTax(50000));

        // Over 120,000 PLN in 32% bracket
        self::assertSame(17200.0, $calculator->progressiveAnnualTax(140000));
    }
}
