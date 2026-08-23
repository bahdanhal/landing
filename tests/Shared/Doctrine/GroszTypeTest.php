<?php

declare(strict_types=1);

namespace App\Tests\Shared\Doctrine;

use App\Shared\Domain\Grosz;
use App\Shared\Infrastructure\Doctrine\Type\GroszType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;

final class GroszTypeTest extends TestCase
{
    private GroszType $type;
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        if (!Type::hasType(GroszType::NAME)) {
            Type::addType(GroszType::NAME, GroszType::class);
        }

        /** @var GroszType $type */
        $type = Type::getType(GroszType::NAME);
        $this->type = $type;
        $this->platform = $this->createStub(AbstractPlatform::class);
    }

    public function testConvertToDatabaseValue(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
        self::assertSame(150000, $this->type->convertToDatabaseValue(Grosz::fromGrosz(150000), $this->platform));
        self::assertSame(25000, $this->type->convertToDatabaseValue(25000, $this->platform));
    }

    public function testConvertToPHPValue(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));

        $vo = $this->type->convertToPHPValue(35000, $this->platform);
        self::assertInstanceOf(Grosz::class, $vo);
        self::assertSame(35000, $vo->amount);
    }

    public function testGetName(): void
    {
        self::assertSame('grosz', $this->type->getName());
    }
}
