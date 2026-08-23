<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Mcp\PkpassTools;
use App\Pkpass\Application\PkpassInspector;
use App\Pkpass\Domain\Engine\PkpassValidator;
use PHPUnit\Framework\TestCase;

final class PkpassToolsTest extends TestCase
{
    public function testInspectApplePkpassToolReturnsValidationJson(): void
    {
        $validator = new PkpassValidator();
        $inspector = new PkpassInspector($validator);
        $tools = new PkpassTools($inspector);

        $validPass = json_encode([
            'formatVersion' => 1,
            'passTypeIdentifier' => 'pass.com.acme.card',
            'serialNumber' => 'GEN-100',
            'teamIdentifier' => 'ABCDE12345',
            'organizationName' => 'Acme Club',
            'description' => 'Membership Card',
            'generic' => [
                'primaryFields' => [
                    ['key' => 'member', 'label' => 'MEMBER', 'value' => 'Bahdan'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $tools->inspectApplePkpass($validPass);
        $data = json_decode($response, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('completed', $data['status']);
        self::assertTrue($data['result']['is_valid']);
        self::assertSame('generic', $data['result']['pass_type']);
        self::assertSame('Acme Club', $data['result']['organization_name']);
    }

    public function testInspectApplePkpassToolHandlesInvalidJson(): void
    {
        $validator = new PkpassValidator();
        $inspector = new PkpassInspector($validator);
        $tools = new PkpassTools($inspector);

        $response = $tools->inspectApplePkpass('{ broken json');
        $data = json_decode($response, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('completed', $data['status']);
        self::assertFalse($data['result']['is_valid']);
        self::assertSame('ERR_INVALID_JSON', $data['result']['findings'][0]['code']);
    }
}
