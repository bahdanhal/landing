<?php

namespace App\Tests\Service;

use App\Service\RobotsPolicy;
use PHPUnit\Framework\TestCase;

final class RobotsPolicyTest extends TestCase
{
    public function testAppliesAllowDisallowAndCrawlDelay(): void
    {
        $parser = new RobotsPolicy();
        $policy = $parser->parse("User-agent: *\nDisallow: /private/\nAllow: /private/public\nDisallow: /*?preview=*$\nCrawl-delay: 2");

        self::assertFalse($parser->allows('https://example.com/private/report', $policy));
        self::assertTrue($parser->allows('https://example.com/private/public', $policy));
        self::assertTrue($parser->allows('https://example.com/articles', $policy));
        self::assertFalse($parser->allows('https://example.com/article?preview=yes', $policy));
        self::assertSame(2000, $policy['crawl_delay_ms']);
    }
}
