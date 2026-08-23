<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Audit\Infrastructure\AuditLogger;
use PHPUnit\Framework\TestCase;

final class AuditLoggerTest extends TestCase
{
    public function testWritesReadableJsonWithoutQueryValues(): void
    {
        $directory = sys_get_temp_dir() . '/seo-audit-logger-' . bin2hex(random_bytes(4));
        $logger = new AuditLogger($directory, 14);

        try {
            $logger->log('audit_requested', [
                'audit_id' => 'test-audit',
                'target' => $logger->safeUrl('https://example.com/search?token=secret-value&sort=price'),
                'error' => $logger->safeError('Failed at https://example.com/search?token=secret-value'),
            ]);

            $files = glob($directory . '/audit-*.jsonl') ?: [];
            self::assertCount(1, $files);
            $contents = file_get_contents($files[0]);
            self::assertIsString($contents);
            self::assertStringContainsString('"event":"audit_requested"', $contents);
            self::assertStringContainsString('https://example.com/search?sort&token', $contents);
            self::assertStringNotContainsString('secret-value', $contents);
            self::assertStringNotContainsString('sort=price', $contents);

            $events = $logger->events();
            self::assertCount(1, $events);
            self::assertSame('audit_requested', $events[0]['event']);
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
    }
}
