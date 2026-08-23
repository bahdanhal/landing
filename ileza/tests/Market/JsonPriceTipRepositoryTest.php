<?php

declare(strict_types=1);

namespace App\Tests\Market;

use App\Market\Infrastructure\JsonPriceTipRepository;
use PHPUnit\Framework\TestCase;

final class JsonPriceTipRepositoryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/price-tip-' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/price-tips/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->directory . '/price-tips');
        @rmdir($this->directory);
    }

    public function testStoresOnlyNormalizedPrivateReviewData(): void
    {
        $repository = new JsonPriceTipRepository($this->directory, 'test-secret');
        $tip = $repository->submit(
            'iphone-13-128gb',
            'https://market.example/listing/123?tracking=secret#seller',
            'Person@Example.com',
            '203.0.113.8',
        );

        self::assertSame('https://market.example/listing/123', $tip->listingUrl);
        self::assertSame('person@example.com', $tip->email);
        self::assertNotSame('203.0.113.8', $tip->ipHash);
        self::assertLessThanOrEqual(91, $tip->submittedAt->diff($tip->expiresAt)->days);
        self::assertCount(1, $repository->all());
    }

    public function testRejectsCredentialsAndUnsupportedSchemes(): void
    {
        $repository = new JsonPriceTipRepository($this->directory, 'test-secret');

        $this->expectException(\InvalidArgumentException::class);
        $repository->submit('iphone-13-128gb', 'file:///etc/passwd', '', '203.0.113.8');
    }
}
