<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\Grosz;
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
}
