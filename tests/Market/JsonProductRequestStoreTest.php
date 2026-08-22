<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Infrastructure\JsonProductRequestStore;
use PHPUnit\Framework\TestCase;

final class JsonProductRequestStoreTest extends TestCase
{
    public function testItStoresAProductRequestWithoutRawIp(): void
    {
        $directory = sys_get_temp_dir() . '/product-request-' . bin2hex(random_bytes(4));
        try {
            (new JsonProductRequestStore($directory, 'secret'))->save('Peugeot 206 CC', 'Person@example.com', '203.0.113.4');
            $files = glob($directory . '/requests/*.jsonl') ?: [];
            self::assertCount(1, $files);
            $record = json_decode(trim((string) file_get_contents($files[0])), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('Peugeot 206 CC', $record['product']);
            self::assertSame('person@example.com', $record['email']);
            self::assertNotSame('203.0.113.4', $record['ip_hash']);
        } finally {
            foreach (glob($directory . '/requests/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory . '/requests');
            @rmdir($directory);
        }
    }
}
