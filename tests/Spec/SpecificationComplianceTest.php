<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use App\CaddyTranspiler\Application\CaddyTranspiler;
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
        self::assertCount(6, $spec['editorial_advisories']);

        foreach ($spec['rules'] as $rule) {
            self::assertArrayHasKey('code', $rule);
            self::assertArrayHasKey('severity', $rule);
            self::assertContains($rule['severity'], $spec['severities']);
            self::assertArrayHasKey('title', $rule);
            self::assertArrayHasKey('description', $rule);
        }

        foreach ($spec['editorial_advisories'] as $advisory) {
            self::assertArrayHasKey('code', $advisory);
            self::assertArrayHasKey('title', $advisory);
            self::assertArrayHasKey('description', $advisory);
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
        self::assertCount(15, $spec['tools']);

        $names = array_column($spec['tools'], 'name');
        self::assertContains('list_polish_used_price_products', $names);
        self::assertContains('get_polish_used_price_history', $names);
        self::assertContains('audit_website_seo', $names);
        self::assertContains('analyze_geo_readiness', $names);
        self::assertContains('calculate_polish_income_comparison', $names);
        self::assertContains('update_polish_used_price_observation', $names);
        self::assertContains('get_admin_dashboard_statistics', $names);
        self::assertContains('list_admin_contact_leads', $names);
        self::assertContains('list_admin_product_requests', $names);
        self::assertContains('list_admin_price_tips', $names);
        self::assertContains('list_admin_recent_audits', $names);
        self::assertContains('inspect_domain_security', $names);
        self::assertContains('transpile_to_caddyfile', $names);
        self::assertContains('inspect_apple_pkpass', $names);
        self::assertContains('calculate_cidr_overlap', $names);
    }

    public function testCidrMatrixSpecStructure(): void
    {
        $specPath = dirname(__DIR__, 2) . '/specs/cidr-matrix.spec.json';
        self::assertFileExists($specPath);

        $spec = json_decode((string) file_get_contents($specPath), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotEmpty($spec['supported_ip_versions']);
        self::assertNotEmpty($spec['diagnostic_codes']);
        self::assertNotEmpty($spec['presets']);
        self::assertNotEmpty($spec['test_vectors']);
    }

    public function testPkpassInspectorSpecCompliance(): void
    {
        $specPath = dirname(__DIR__, 2) . '/specs/pkpass-inspector.spec.json';
        self::assertFileExists($specPath);

        $spec = json_decode((string) file_get_contents($specPath), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotEmpty($spec['pass_styles']);
        self::assertNotEmpty($spec['transit_types']);
        self::assertNotEmpty($spec['barcode_formats']);
        self::assertNotEmpty($spec['diagnostic_codes']);
        self::assertNotEmpty($spec['test_vectors']);

        $validator = new \App\Pkpass\Domain\Engine\PkpassValidator();

        foreach ($spec['test_vectors'] as $vector) {
            $result = $validator->validate($vector['pass']);
            self::assertSame(
                $vector['expected_valid'],
                $result->isValid,
                "Expected validation match for vector: {$vector['description']}"
            );

            if (isset($vector['expected_pass_type'])) {
                self::assertSame($vector['expected_pass_type'], $result->passType?->value);
            }

            if (isset($vector['expected_error_codes'])) {
                $codes = array_map(static fn ($f) => $f->code, $result->findings);
                foreach ($vector['expected_error_codes'] as $expectedCode) {
                    self::assertContains($expectedCode, $codes, "Missing code {$expectedCode} in {$vector['description']}");
                }
            }
        }
    }

    public function testDomainInspectorSpecStructure(): void
    {
        $specPath = dirname(__DIR__, 2) . '/specs/domain-inspector.spec.json';
        self::assertFileExists($specPath);

        $spec = json_decode((string) file_get_contents($specPath), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotEmpty($spec['protocols']);
        self::assertSame(100, $spec['scoring']['max_score']);

        $protocolNames = array_column($spec['protocols'], 'name');
        self::assertContains('dmarc', $protocolNames);
        self::assertContains('bimi', $protocolNames);
        self::assertContains('mta_sts', $protocolNames);
        self::assertContains('tls_rpt', $protocolNames);
        self::assertContains('spf', $protocolNames);
    }

    public function testCaddyTranspilerSpecCompliance(): void
    {
        $specPath = dirname(__DIR__, 2) . '/specs/caddy-transpiler.spec.json';
        self::assertFileExists($specPath);

        $spec = json_decode((string) file_get_contents($specPath), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotEmpty($spec['supported_source_types']);
        self::assertNotEmpty($spec['presets']);
        self::assertNotEmpty($spec['advisory_rules']);
        self::assertNotEmpty($spec['test_vectors']);

        $transpiler = new CaddyTranspiler();

        foreach ($spec['test_vectors'] as $vector) {
            $result = $transpiler->transpile($vector['input']);
            foreach ($vector['expected_caddyfile_snippets'] as $snippet) {
                self::assertStringContainsString(
                    $snippet,
                    $result->caddyfile,
                    "Expected Caddyfile to contain '{$snippet}' for vector: {$vector['description']}"
                );
            }
        }
    }

    public function testCidrMatrixSpecCompliance(): void
    {
        $specPath = dirname(__DIR__, 2) . '/specs/cidr-matrix.spec.json';
        self::assertFileExists($specPath);

        $spec = json_decode((string) file_get_contents($specPath), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotEmpty($spec['supported_ip_versions']);
        self::assertNotEmpty($spec['diagnostic_codes']);
        self::assertNotEmpty($spec['presets']);
        self::assertNotEmpty($spec['test_vectors']);

        $calculator = new \App\CidrMatrix\Domain\Engine\CidrCalculator();

        foreach ($spec['test_vectors'] as $vector) {
            $parentCidr = $vector['parent_cidr'] ?? null;
            $requestedPrefix = $vector['requested_prefix'] ?? null;

            $result = $calculator->analyze(
                cidrInputs: $vector['cidrs'],
                requestedFreePrefix: $requestedPrefix,
                parentCidrInput: $parentCidr
            );

            if (isset($vector['expected_has_collisions'])) {
                self::assertSame(
                    $vector['expected_has_collisions'],
                    $result->hasCollisions,
                    "Collision match failed for: {$vector['description']}"
                );
            }

            if (isset($vector['expected_collision_count'])) {
                self::assertSame(
                    $vector['expected_collision_count'],
                    $result->collisionCount,
                    "Collision count match failed for: {$vector['description']}"
                );
            }

            if (isset($vector['expected_collisions'])) {
                $actualCollisions = array_map(static fn ($c) => $c->toArray(), $result->collisions);
                self::assertSame(
                    $vector['expected_collisions'],
                    $actualCollisions,
                    "Collisions array mismatch for: {$vector['description']}"
                );
            }

            if (isset($vector['expected_normalized_cidr'])) {
                self::assertNotEmpty($result->parsedBlocks);
                self::assertSame(
                    $vector['expected_normalized_cidr'],
                    $result->parsedBlocks[0]->normalizedCidr,
                    "Normalized CIDR mismatch for: {$vector['description']}"
                );
            }

            if (isset($vector['expected_warnings'])) {
                foreach ($vector['expected_warnings'] as $warningCode) {
                    self::assertContains(
                        $warningCode,
                        $result->warnings,
                        "Missing warning code {$warningCode} for: {$vector['description']}"
                    );
                }
            }

            if (isset($vector['expected_free_cidr'])) {
                self::assertSame(
                    $vector['expected_free_cidr'],
                    $result->freeSubnetCidr,
                    "Free CIDR allocation mismatch for: {$vector['description']}"
                );
            }
        }
    }
}
