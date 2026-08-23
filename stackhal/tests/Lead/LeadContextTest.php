<?php

declare(strict_types=1);

namespace App\Tests\Lead;

use App\Lead\Application\CaptureLead;
use App\Lead\Domain\Lead;
use App\Lead\Infrastructure\JsonlLeadRepository;
use PHPUnit\Framework\TestCase;

final class LeadContextTest extends TestCase
{
    public function testLeadDomainValidation(): void
    {
        $lead = Lead::create('Test@Example.Com', '', 'Please call me.', 'fake-hash', 'seo-tool');
        self::assertSame('test@example.com', $lead->email);
        self::assertSame('fake-hash', $lead->ipHash);
        self::assertSame('seo-tool', $lead->source);

        $this->expectException(\InvalidArgumentException::class);
        Lead::create('not-an-email', '', '', 'fake-hash', 'seo-tool');
    }

    public function testCaptureLeadUseCaseWithJsonlRepository(): void
    {
        $directory = sys_get_temp_dir() . '/lead-test-' . bin2hex(random_bytes(4));
        $repo = new JsonlLeadRepository($directory);
        $useCase = new CaptureLead($repo, 'secret-salt');

        try {
            $lead = $useCase->execute('User@Example.com', '+48 500 000 000', 'Audit request', '198.51.100.42', 'geo-audit');
            self::assertSame('user@example.com', $lead->email);
            self::assertSame('geo-audit', $lead->source);

            $files = glob($directory . '/leads-*.jsonl') ?: [];
            self::assertCount(1, $files);
            $content = json_decode((string) file_get_contents($files[0]), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('user@example.com', $content['email']);
            self::assertSame('+48 500 000 000', $content['phone']);
            self::assertSame('geo-audit', $content['source']);
            self::assertNotSame('198.51.100.42', $content['ip_hash']);

            $savedLeads = $repo->all();
            self::assertCount(1, $savedLeads);
            self::assertSame('+48 500 000 000', $savedLeads[0]->phone);
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
    }
}
