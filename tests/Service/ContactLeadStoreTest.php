<?php

namespace App\Tests\Service;

use App\Service\ContactLeadStore;
use PHPUnit\Framework\TestCase;

final class ContactLeadStoreTest extends TestCase
{
    public function testStoresEmailWithHashedIpAndSanitizedSource(): void
    {
        $directory = sys_get_temp_dir().'/seo-contact-leads-'.bin2hex(random_bytes(4));
        $store = new ContactLeadStore($directory, 'test-secret');

        try {
            $store->store(' Person@Example.COM ', '203.0.113.8', 'audit report<script>');
            $files = glob($directory.'/leads-*.jsonl');
            self::assertCount(1, $files);
            $record = json_decode(trim((string) file_get_contents($files[0])), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('person@example.com', $record['email']);
            self::assertSame('auditreportscript', $record['source']);
            self::assertNotSame('203.0.113.8', $record['ip_hash']);
            self::assertArrayHasKey('timestamp', $record);
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
    }
}
