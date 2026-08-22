<?php

declare(strict_types=1);

namespace App\Tests\DomainInspector;

use App\DomainInspector\Domain\DmarcCheck;
use PHPUnit\Framework\TestCase;

final class DmarcCheckTest extends TestCase
{
    public function testMissingDmarcRecord(): void
    {
        $check = DmarcCheck::fromTxtRecords('example.com', []);
        self::assertFalse($check->hasRecord);
        self::assertSame('fail', $check->status);
        self::assertFalse($check->isBimiCompliant);
        self::assertNotNull($check->recommendedFix);
        self::assertStringContainsString('p=reject', $check->recommendedFix);
    }

    public function testStrictRejectPolicyIsBimiCompliant(): void
    {
        $check = DmarcCheck::fromTxtRecords('example.com', [
            'v=DMARC1; p=reject; pct=100; rua=mailto:dmarc@example.com;',
        ]);
        self::assertTrue($check->hasRecord);
        self::assertSame('pass', $check->status);
        self::assertTrue($check->isBimiCompliant);
        self::assertSame('reject', $check->tags['p']);
    }

    public function testQuarantinePct100IsBimiCompliant(): void
    {
        $check = DmarcCheck::fromTxtRecords('example.com', [
            'v=DMARC1; p=quarantine; pct=100; rua=mailto:dmarc@example.com;',
        ]);
        self::assertTrue($check->hasRecord);
        self::assertSame('pass', $check->status);
        self::assertTrue($check->isBimiCompliant);
    }

    public function testQuarantinePct50IsNotBimiCompliant(): void
    {
        $check = DmarcCheck::fromTxtRecords('example.com', [
            'v=DMARC1; p=quarantine; pct=50; rua=mailto:dmarc@example.com;',
        ]);
        self::assertTrue($check->hasRecord);
        self::assertSame('warning', $check->status);
        self::assertFalse($check->isBimiCompliant);
    }

    public function testPolicyNoneIsNotBimiCompliant(): void
    {
        $check = DmarcCheck::fromTxtRecords('example.com', [
            'v=DMARC1; p=none; rua=mailto:dmarc@example.com;',
        ]);
        self::assertTrue($check->hasRecord);
        self::assertSame('warning', $check->status);
        self::assertFalse($check->isBimiCompliant);
    }
}
