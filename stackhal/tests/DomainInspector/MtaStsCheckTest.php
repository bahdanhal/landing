<?php

declare(strict_types=1);

namespace App\Tests\DomainInspector;

use App\DomainInspector\Domain\MtaStsCheck;
use PHPUnit\Framework\TestCase;

final class MtaStsCheckTest extends TestCase
{
    public function testMissingMtaStsRecord(): void
    {
        $check = MtaStsCheck::evaluate('example.com', []);
        self::assertFalse($check->hasDnsRecord);
        self::assertSame('fail', $check->status);
        self::assertFalse($check->isPolicyFileReachable);
    }

    public function testEnforceModePolicy(): void
    {
        $policyFile = "version: STSv1\nmode: enforce\nmx: mail.example.com\nmax_age: 604800\n";
        $check = MtaStsCheck::evaluate(
            'example.com',
            ['v=STSv1; id=202608230000;'],
            isPolicyFileReachable: true,
            policyFileContent: $policyFile,
        );

        self::assertTrue($check->hasDnsRecord);
        self::assertSame('pass', $check->status);
        self::assertTrue($check->isPolicyFileReachable);
        self::assertSame('enforce', $check->policyMode);
    }

    public function testTestingModePolicy(): void
    {
        $policyFile = "version: STSv1\nmode: testing\nmx: mail.example.com\nmax_age: 604800\n";
        $check = MtaStsCheck::evaluate(
            'example.com',
            ['v=STSv1; id=202608230000;'],
            isPolicyFileReachable: true,
            policyFileContent: $policyFile,
        );

        self::assertTrue($check->hasDnsRecord);
        self::assertSame('pass', $check->status);
        self::assertSame('testing', $check->policyMode);
    }

    public function testDnsRecordExistsWithoutPolicyFile(): void
    {
        $check = MtaStsCheck::evaluate(
            'example.com',
            ['v=STSv1; id=202608230000;'],
            isPolicyFileReachable: false,
        );

        self::assertTrue($check->hasDnsRecord);
        self::assertSame('warning', $check->status);
        self::assertFalse($check->isPolicyFileReachable);
    }
}
