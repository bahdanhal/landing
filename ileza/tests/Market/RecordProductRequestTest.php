<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Application\RecordProductRequest;
use App\Market\Domain\ProductRequestStore;
use PHPUnit\Framework\TestCase;

final class RecordProductRequestTest extends TestCase
{
    public function testRecordsValidProductRequest(): void
    {
        $store = $this->createMock(ProductRequestStore::class);
        $store->expects(self::once())
            ->method('save')
            ->with('iPad Air M2', 'user@example.com', '127.0.0.1');

        $service = new RecordProductRequest($store);
        $service->execute('  iPad Air M2  ', '  USER@example.com  ', '127.0.0.1');
    }

    public function testThrowsExceptionForEmptyProductName(): void
    {
        $store = $this->createStub(ProductRequestStore::class);
        $service = new RecordProductRequest($store);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product name must be between 1 and 160 characters');

        $service->execute('   ', 'user@example.com', '127.0.0.1');
    }

    public function testThrowsExceptionForInvalidEmail(): void
    {
        $store = $this->createStub(ProductRequestStore::class);
        $service = new RecordProductRequest($store);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address');

        $service->execute('iPad Mini', 'invalid-email-string', '127.0.0.1');
    }
}
