<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\Grosz;
use App\Shared\Domain\HashedIp;
use App\Shared\Domain\SafeUrl;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function testGroszArithmeticAndFormatting(): void
    {
        $g1 = Grosz::fromGrosz(150000); // 1500.00 PLN
        $g2 = Grosz::fromZloty(250.50); // 25050 grosz

        self::assertSame(1500.0, $g1->toPln());
        self::assertSame(25050, $g2->amount);

        $sum = $g1->add($g2);
        self::assertSame(175050, $sum->amount);
        self::assertSame('1 750,50 zł', $sum->toFormattedPln('pl'));
        self::assertSame('PLN 1,750.50', $sum->toFormattedPln('en'));

        $diff = $g1->subtract($g2);
        self::assertSame(124950, $diff->amount);

        $multiplied = $g2->multiply(2);
        self::assertSame(50100, $multiplied->amount);

        self::assertTrue($g1->isGreaterThan($g2));
        self::assertFalse($g1->isLessThan($g2));
        self::assertFalse($g1->isZero());
        self::assertTrue(Grosz::fromGrosz(0)->isZero());
        self::assertTrue($g1->equals(Grosz::fromZloty(1500.00)));
    }

    public function testHashedIpDeterminismAndSecurity(): void
    {
        $secret = 'test-secret-key-123';
        $ip1 = new HashedIp('192.168.1.1', $secret);
        $ip2 = new HashedIp('192.168.1.1', $secret);
        $ip3 = new HashedIp('10.0.0.1', $secret);

        self::assertSame($ip1->toString(), $ip2->toString());
        self::assertNotSame($ip1->toString(), $ip3->toString());
        self::assertTrue($ip1->equals($ip2));
        self::assertFalse($ip1->equals($ip3));
        self::assertStringNotContainsString('192.168.1.1', $ip1->toString());
    }

    public function testSafeUrlNormalizationAndStripping(): void
    {
        $url1 = new SafeUrl('example.com/page?query=secret#fragment');
        self::assertSame('https://example.com/page', $url1->toString());
        self::assertSame('example.com', $url1->getHost());
        self::assertSame('https', $url1->getScheme());

        $url2 = new SafeUrl('http://sub.domain.org:8080/path/to/resource?ref=track');
        self::assertSame('http://sub.domain.org:8080/path/to/resource', $url2->toString());
        self::assertSame('sub.domain.org', $url2->getHost());
    }

    public function testSafeUrlRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SafeUrl('   ');
    }
}
