<?php

declare(strict_types=1);

namespace App\Tests\DomainInspector;

use App\DomainInspector\Domain\BimiCheck;
use PHPUnit\Framework\TestCase;

final class BimiCheckTest extends TestCase
{
    public function testMissingBimiRecord(): void
    {
        $check = BimiCheck::fromTxtRecords('example.com', []);
        self::assertFalse($check->hasRecord);
        self::assertSame('fail', $check->status);
        self::assertNull($check->logoUrl);
        self::assertNotNull($check->recommendedFix);
    }

    public function testValidBimiRecordWithHttpsLogo(): void
    {
        $check = BimiCheck::fromTxtRecords('example.com', [
            'v=BIMI1; l=https://example.com/logo-bimi.svg; a=https://example.com/cert.pem;',
        ], isSvgReachable: true, svgContentType: 'image/svg+xml', isSvgTinyPs: true);

        self::assertTrue($check->hasRecord);
        self::assertSame('https://example.com/logo-bimi.svg', $check->logoUrl);
        self::assertSame('https://example.com/cert.pem', $check->certificateUrl);
        self::assertSame('pass', $check->status);
        self::assertTrue($check->isSvgReachable);
        self::assertTrue($check->isSvgTinyPs);
    }

    public function testBimiRecordWithUnreachableSvg(): void
    {
        $check = BimiCheck::fromTxtRecords('example.com', [
            'v=BIMI1; l=https://example.com/broken.svg;',
        ], isSvgReachable: false);

        self::assertTrue($check->hasRecord);
        self::assertSame('warning', $check->status);
        self::assertFalse($check->isSvgReachable);
    }

    public function testBimiRecordWithNonHttpsLogo(): void
    {
        $check = BimiCheck::fromTxtRecords('example.com', [
            'v=BIMI1; l=http://example.com/logo.svg;',
        ]);

        self::assertTrue($check->hasRecord);
        self::assertSame('warning', $check->status);
        self::assertStringContainsString('HTTPS', $check->summary);
    }
}
