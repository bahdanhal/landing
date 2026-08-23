<?php

declare(strict_types=1);

namespace App\Tests\Spec;

use PHPUnit\Framework\TestCase;

final class SpecificationComplianceTest extends TestCase
{
    public function testMcpToolsSpecStructure(): void
    {
        $specPath = dirname(__DIR__, 2) . '/specs/mcp-tools.spec.json';
        self::assertFileExists($specPath);

        $spec = json_decode((string) file_get_contents($specPath), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(2, $spec['tools']);

        $names = array_column($spec['tools'], 'name');
        self::assertContains('get_admin_dashboard_statistics', $names);
        self::assertContains('list_admin_contact_leads', $names);
    }
}
