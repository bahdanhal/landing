<?php

declare(strict_types=1);

namespace App\Tests\CidrMatrix;

use App\CidrMatrix\Domain\Engine\CidrCalculator;
use App\CidrMatrix\Domain\Model\CidrBlock;
use PHPUnit\Framework\TestCase;

final class CidrCalculatorTest extends TestCase
{
    private CidrCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new CidrCalculator();
    }

    public function testCidrBlockParsingAndNormalization(): void
    {
        $block = CidrBlock::parse('10.0.0.45/24');
        self::assertNotNull($block);
        self::assertFalse($block->isCanonical);
        self::assertSame('10.0.0.0/24', $block->normalizedCidr);
        self::assertSame('10.0.0.0', $block->networkIp);
        self::assertSame('10.0.0.255', $block->broadcastIp);
        self::assertSame('256', $block->totalHosts);
    }

    public function testIPv6ParsingAndNormalization(): void
    {
        $block = CidrBlock::parse('2001:db8::1/32');
        self::assertNotNull($block);
        self::assertFalse($block->isCanonical);
        self::assertSame('2001:db8::/32', $block->normalizedCidr);
    }

    public function testOverlapCollisionDetection(): void
    {
        $result = $this->calculator->analyze(['10.0.0.0/16', '10.0.32.0/20']);
        self::assertTrue($result->hasCollisions);
        self::assertSame(1, $result->collisionCount);
        self::assertCount(1, $result->collisions);

        $col = $result->collisions[0];
        self::assertSame('10.0.0.0/16', $col->cidrA);
        self::assertSame('10.0.32.0/20', $col->cidrB);
        self::assertSame('10.0.32.0', $col->startIp);
        self::assertSame('10.0.47.255', $col->endIp);
        self::assertSame('10.0.32.0/20', $col->overlapCidr);
    }

    public function testCleanNonOverlappingSubnets(): void
    {
        $result = $this->calculator->analyze(['10.0.0.0/24', '10.0.1.0/24']);
        self::assertFalse($result->hasCollisions);
        self::assertSame(0, $result->collisionCount);
        self::assertEmpty($result->collisions);
    }

    public function testFreeSubnetAllocation(): void
    {
        $result = $this->calculator->analyze(
            cidrInputs: ['10.0.0.0/20'],
            requestedFreePrefix: 20,
            parentCidrInput: '10.0.0.0/16'
        );

        self::assertSame('10.0.16.0/20', $result->freeSubnetCidr);
    }

    public function testInvalidCidrDiagnostic(): void
    {
        $result = $this->calculator->analyze(['invalid-cidr-string']);
        self::assertCount(1, $result->diagnostics);
        self::assertSame('ERR_INVALID_CIDR', $result->diagnostics[0]->code);
    }
}
