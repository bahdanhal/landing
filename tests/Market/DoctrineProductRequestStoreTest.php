<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Infrastructure\DoctrineProductRequestStore;
use App\Tests\DoctrineTestCase;

final class DoctrineProductRequestStoreTest extends DoctrineTestCase
{
    public function testSavesAndListsProductRequests(): void
    {
        $store = new DoctrineProductRequestStore($this->entityManager, 'secret');

        self::assertEmpty($store->all());

        $store->save('Sony WH-1000XM5', 'user@example.com', '127.0.0.1');
        $store->save('MacBook Air M2', 'user2@example.com', '127.0.0.2');

        $all = $store->all();
        self::assertCount(2, $all);
        self::assertSame('MacBook Air M2', $all[0]['product']);
        self::assertSame('Sony WH-1000XM5', $all[1]['product']);
    }
}
