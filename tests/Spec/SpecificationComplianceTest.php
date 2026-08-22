<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\Income\Domain\PolishIncomeCalculator;
use PHPUnit\Framework\TestCase;

final class SpecificationComplianceTest extends TestCase
{
    public function testIncomeCalculatorComplianceWithSpec(): void
    {
        $specPath = dirname(__DIR__, 2) . '/specs/income-calculator.spec.json';
        self::assertFileExists($specPath);

        $spec = json_decode((string) file_get_contents($specPath), true, flags: JSON_THROW_ON_ERROR);
        $calculator = new PolishIncomeCalculator();

        foreach ($spec['test_vectors'] as $vector) {
            if (isset($vector['input'], $vector['expected'])) {
                $actual = $calculator->compare($vector['input']);

                self::assertSame(
                    $vector['expected']['employment']['net'],
                    $actual['employment']['net'],
                    "Employment net must match spec vector for: " . $vector['description']
                );
                self::assertSame(
                    $vector['expected']['b2b']['net'],
                    $actual['b2b']['net'],
                    "B2B net must match spec vector for: " . $vector['description']
                );
            }

            if (isset($vector['tax_brackets'])) {
                foreach ($vector['tax_brackets'] as $bracket) {
                    $tax = $calculator->progressiveAnnualTax((float) $bracket['base']);
                    self::assertSame(
                        $bracket['expected_annual_tax'],
                        $tax,
                        "Annual progressive tax for base {$bracket['base']} must match spec"
                    );
                }
            }
        }
    }

    public function testSeoAuditRulesSpecStructure(): void
    {
        $specPath = dirname(__DIR__, 2) . '/specs/seo-audit-rules.spec.json';
        self::assertFileExists($specPath);

        $spec = json_decode((string) file_get_contents($specPath), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotEmpty($spec['rules']);

        foreach ($spec['rules'] as $rule) {
            self::assertArrayHasKey('code', $rule);
            self::assertArrayHasKey('severity', $rule);
            self::assertContains($rule['severity'], $spec['severities']);
            self::assertArrayHasKey('title', $rule);
            self::assertArrayHasKey('description', $rule);
        }
    }

    public function testGeoReadinessSpecStructure(): void
    {
        $specPath = dirname(__DIR__, 2) . '/specs/geo-readiness.spec.json';
        self::assertFileExists($specPath);

        $spec = json_decode((string) file_get_contents($specPath), true, flags: JSON_THROW_ON_ERROR);
        self::assertContains('GPTBot', $spec['crawler_user_agents']);
        self::assertContains('ClaudeBot', $spec['crawler_user_agents']);
        self::assertNotEmpty($spec['schema_classifications']['content']);
        self::assertNotEmpty($spec['schema_classifications']['entity']);
    }

    public function testMcpToolsSpecStructure(): void
    {
        $specPath = dirname(__DIR__, 2) . '/specs/mcp-tools.spec.json';
        self::assertFileExists($specPath);

        $spec = json_decode((string) file_get_contents($specPath), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(6, $spec['tools']);

        $names = array_column($spec['tools'], 'name');
        self::assertContains('list_polish_used_price_products', $names);
        self::assertContains('get_polish_used_price_history', $names);
        self::assertContains('audit_website_seo', $names);
        self::assertContains('analyze_geo_readiness', $names);
        self::assertContains('calculate_polish_income_comparison', $names);
        self::assertContains('update_polish_used_price_observation', $names);
    }
}
