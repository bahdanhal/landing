<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Infrastructure\DoctrinePriceTipRepository;
use App\Tests\DoctrineTestCase;

final class DoctrinePriceTipRepositoryTest extends DoctrineTestCase
{
    public function testSubmitsAndRetrievesTips(): void
    {
        $repository = new DoctrinePriceTipRepository($this->entityManager, 'secret');

        self::assertEmpty($repository->all());

        $tip = $repository->submit(
            'iphone-13-128gb',
            'https://allegro.pl/oferta/iphone-13-128gb-123456?tracking=remove',
            'user@example.com',
            '127.0.0.1'
        );

        self::assertSame('iphone-13-128gb', $tip->productSlug);
        self::assertSame('https://allegro.pl/oferta/iphone-13-128gb-123456', $tip->listingUrl);

        $all = $repository->all();
        self::assertCount(1, $all);
        self::assertSame('https://allegro.pl/oferta/iphone-13-128gb-123456', $all[0]->listingUrl);
    }
}
