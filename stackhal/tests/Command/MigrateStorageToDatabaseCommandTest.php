<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\MigrateStorageToDatabaseCommand;
use App\Entity\LeadEntity;
use App\Entity\PageViewEntity;
use App\Tests\DoctrineTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MigrateStorageToDatabaseCommandTest extends DoctrineTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/migrate_test_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . '/leads', 0777, true);
        mkdir($this->tempDir . '/analytics', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testImportsAllJsonAndJsonlFiles(): void
    {
        // 1. Lead
        $leadLine = json_encode([
            'email' => 'contact@example.com',
            'phone' => '+48 555 123 456',
            'message' => 'Need SEO audit',
            'ip_hash' => 'lead-ip-hash',
            'source' => 'seo-audit',
            'created_at' => '2026-08-21T10:00:00+00:00',
        ], JSON_THROW_ON_ERROR);
        file_put_contents($this->tempDir . '/leads/leads-2026-08-21.jsonl', $leadLine . PHP_EOL);

        // 2. Page View
        $viewLine = json_encode([
            'occurred_at' => '2026-08-22T08:30:00+00:00',
            'visitor_hash' => 'view-visitor-hash',
            'path' => '/tools/caddy-transpiler',
            'source' => 'direct',
            'referrer_host' => 'google.com',
        ], JSON_THROW_ON_ERROR);
        file_put_contents($this->tempDir . '/analytics/2026-08-22.jsonl', $viewLine . PHP_EOL);

        $command = new MigrateStorageToDatabaseCommand(
            $this->entityManager,
            $this->tempDir . '/leads',
            $this->tempDir . '/analytics'
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $normalizedDisplay = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('Migration completed: 1 leads, 1 page views.', $normalizedDisplay);

        self::assertCount(1, $this->entityManager->getRepository(LeadEntity::class)->findAll());
        self::assertCount(1, $this->entityManager->getRepository(PageViewEntity::class)->findAll());
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
