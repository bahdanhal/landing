<?php

declare(strict_types=1);

namespace App\Tests\DomainInspector;

use App\DomainInspector\Domain\TlsRptCheck;
use PHPUnit\Framework\TestCase;

final class TlsRptCheckTest extends TestCase
{
    public function testMissingTlsRptRecord(): void
    {
        $check = TlsRptCheck::fromTxtRecords('example.com', []);
        self::assertFalse($check->hasRecord);
        self::assertSame('fail', $check->status);
        self::assertNull($check->rua);
    }

    public function testValidTlsRptRecord(): void
    {
        $check = TlsRptCheck::fromTxtRecords('example.com', [
            'v=TLSRPTv1; rua=mailto:tls-reports@example.com;',
        ]);
        self::assertTrue($check->hasRecord);
        self::assertSame('pass', $check->status);
        self::assertSame('mailto:tls-reports@example.com', $check->rua);
    }
}
