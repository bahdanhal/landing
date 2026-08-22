<?php

declare(strict_types=1);

namespace App\Tests\DomainInspector;

use App\DomainInspector\Domain\SpfCheck;
use PHPUnit\Framework\TestCase;

final class SpfCheckTest extends TestCase
{
    public function testMissingSpfRecord(): void
    {
        $check = SpfCheck::fromTxtRecords('example.com', []);
        self::assertFalse($check->hasRecord);
        self::assertSame('fail', $check->status);
    }

    public function testStrictHardFailSpf(): void
    {
        $check = SpfCheck::fromTxtRecords('example.com', [
            'v=spf1 include:_spf.google.com -all',
        ]);
        self::assertTrue($check->hasRecord);
        self::assertSame('pass', $check->status);
        self::assertSame('-all', $check->allMechanism);
    }

    public function testSoftFailSpf(): void
    {
        $check = SpfCheck::fromTxtRecords('example.com', [
            'v=spf1 include:_spf.google.com ~all',
        ]);
        self::assertTrue($check->hasRecord);
        self::assertSame('pass', $check->status);
        self::assertSame('~all', $check->allMechanism);
    }

    public function testDangerousPlusAllSpf(): void
    {
        $check = SpfCheck::fromTxtRecords('example.com', [
            'v=spf1 +all',
        ]);
        self::assertTrue($check->hasRecord);
        self::assertSame('fail', $check->status);
        self::assertSame('+all', $check->allMechanism);
    }

    public function testMultipleSpfRecordsForbidden(): void
    {
        $check = SpfCheck::fromTxtRecords('example.com', [
            'v=spf1 include:_spf.google.com ~all',
            'v=spf1 include:mailgun.org ~all',
        ]);
        self::assertTrue($check->hasRecord);
        self::assertSame('fail', $check->status);
        self::assertStringContainsString('Multiple SPF records', $check->summary);
    }
}
