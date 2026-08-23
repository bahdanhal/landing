<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\MigrateStorageToDatabaseCommand;
use App\Entity\LeadEntity;
use App\Entity\PageViewEntity;
use App\Entity\PriceObservationEntity;
use App\Entity\PriceTipEntity;
use App\Entity\ProductRequestEntity;
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
        mkdir($this->tempDir . '/market', 0777, true);
        mkdir($this->tempDir . '/market/requests', 0777, true);
        mkdir($this->tempDir . '/market/price-tips', 0777, true);
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
        // 1. Price observation
        $observationData = [
            'history' => [
                [
                    'product_slug' => 'thinkpad-t14-gen-3-amd',
                    'observed_at' => '2026-08-20T00:00:00+00:00',
                    'median_grosz' => 320000,
                    'low_grosz' => 280000,
                    'high_grosz' => 360000,
                    'sample_size' => 12,
                    'confidence' => 'high',
                    'summary' => 'Stable market price',
                    'methodology' => 'manual',
                ],
            ],
        ];
        file_put_contents(
            $this->tempDir . '/market/thinkpad-t14-gen-3-amd.json',
            json_encode($observationData, JSON_THROW_ON_ERROR)
        );

        // 2. Lead
        $leadLine = json_encode([
            'email' => 'contact@example.com',
            'phone' => '+48 555 123 456',
            'message' => 'Need SEO audit',
            'ip_hash' => 'lead-ip-hash',
            'source' => 'seo-audit',
            'created_at' => '2026-08-21T10:00:00+00:00',
        ], JSON_THROW_ON_ERROR);
        file_put_contents($this->tempDir . '/leads/leads-2026-08-21.jsonl', $leadLine . PHP_EOL);

        // 3. Product Request
        $requestLine = json_encode([
            'product' => 'MacBook Pro M3',
            'email' => 'dev@example.com',
            'ip_hash' => 'request-ip-hash',
            'timestamp' => '2026-08-21T11:00:00+00:00',
        ], JSON_THROW_ON_ERROR);
        file_put_contents($this->tempDir . '/market/requests/product-requests-2026-08-21.jsonl', $requestLine . PHP_EOL);

        // 4. Price Tip
        $tipData = [
            'product_slug' => 'thinkpad-t14-gen-3-amd',
            'listing_url' => 'https://allegro.pl/oferta/example-123',
            'email' => 'user@example.com',
            'ip_hash' => 'tip-ip-hash',
            'submitted_at' => '2026-08-21T12:00:00+00:00',
            'expires_at' => (new \DateTimeImmutable('+30 days'))->format(DATE_ATOM),
        ];
        file_put_contents(
            $this->tempDir . '/market/price-tips/tip-1.json',
            json_encode($tipData, JSON_THROW_ON_ERROR)
        );

        // 5. Page View
        $viewLine = json_encode([
            'occurred_at' => '2026-08-22T08:30:00+00:00',
            'visitor_hash' => 'view-visitor-hash',
            'path' => '/pl/tools',
            'source' => 'direct',
            'referrer_host' => 'google.com',
        ], JSON_THROW_ON_ERROR);
        file_put_contents($this->tempDir . '/analytics/2026-08-22.jsonl', $viewLine . PHP_EOL);

        $command = new MigrateStorageToDatabaseCommand(
            $this->entityManager,
            $this->tempDir . '/market',
            $this->tempDir . '/leads',
            $this->tempDir . '/analytics'
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $normalizedDisplay = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('Migration completed: 1 observations, 1 leads, 1 product requests, 1 price tips, 1 page views.', $normalizedDisplay);

        self::assertCount(1, $this->entityManager->getRepository(PriceObservationEntity::class)->findAll());
        self::assertCount(1, $this->entityManager->getRepository(LeadEntity::class)->findAll());
        self::assertCount(1, $this->entityManager->getRepository(ProductRequestEntity::class)->findAll());
        self::assertCount(1, $this->entityManager->getRepository(PriceTipEntity::class)->findAll());
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
