<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\ProductCatalog;
use App\Market\Application\SubmitCommunityPriceTip;
use App\Market\Domain\PriceTip;
use App\Market\Domain\PriceTipRepository;
use PHPUnit\Framework\TestCase;

final class SubmitCommunityPriceTipTest extends TestCase
{
    public function testSubmitsValidPriceTip(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createMock(PriceTipRepository::class);

        $now = new \DateTimeImmutable('2026-08-20 12:00:00', new \DateTimeZone('UTC'));
        $expectedTip = new PriceTip(
            'iphone-13-128gb',
            'https://allegro.pl/oferta/iphone-13-123456',
            'test@example.com',
            'ip-hash-123',
            $now,
            $now->modify('+90 days')
        );

        $repository->expects(self::once())
            ->method('submit')
            ->with('iphone-13-128gb', 'https://allegro.pl/oferta/iphone-13-123456', 'test@example.com', '1.2.3.4')
            ->willReturn($expectedTip);

        $service = new SubmitCommunityPriceTip($catalog, $repository);
        $result = $service->execute(
            'iphone-13-128gb',
            'https://allegro.pl/oferta/iphone-13-123456',
            'test@example.com',
            '1.2.3.4'
        );

        self::assertSame($expectedTip, $result);
    }

    public function testThrowsExceptionForUnknownProduct(): void
    {
        $catalog = new ProductCatalog();
        $repository = $this->createStub(PriceTipRepository::class);

        $service = new SubmitCommunityPriceTip($catalog, $repository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown product slug');

        $service->execute(
            'unknown-slug',
            'https://allegro.pl/oferta/test',
            'test@example.com',
            '1.2.3.4'
        );
    }
}
