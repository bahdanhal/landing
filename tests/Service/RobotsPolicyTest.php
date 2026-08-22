<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Crawl\Domain\RobotsPolicy;
use PHPUnit\Framework\TestCase;

final class RobotsPolicyTest extends TestCase
{
    public function testAppliesAllowDisallowAndCrawlDelay(): void
    {
        $parser = new RobotsPolicy();
        $robotsTxt = implode("\n", [
            'User-agent: *',
            'Disallow: /private/',
            'Allow: /private/public',
            'Disallow: /*?preview=*$',
            'Crawl-delay: 2',
        ]);
        $policy = $parser->parse($robotsTxt);

        self::assertFalse($parser->allows('https://example.com/private/report', $policy));
        self::assertTrue($parser->allows('https://example.com/private/public', $policy));
        self::assertTrue($parser->allows('https://example.com/articles', $policy));
        self::assertFalse($parser->allows('https://example.com/article?preview=yes', $policy));
        self::assertSame(2000, $policy['crawl_delay_ms']);
    }
}
