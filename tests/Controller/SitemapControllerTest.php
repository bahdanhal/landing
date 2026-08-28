<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Shared\Presentation\Http\SitemapController;
use PHPUnit\Framework\TestCase;

final class SitemapControllerTest extends TestCase
{
    public function testGeneratesValidXmlSitemapWithHeaders(): void
    {
        $controller = new SitemapController();
        $response = $controller();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));
        $content = (string) $response->getContent();
        self::assertStringContainsString('<urlset', $content);
        self::assertStringContainsString('https://bahdanhal.pl/', $content);
        self::assertStringContainsString('https://bahdanhal.pl/pl/', $content);
        self::assertStringNotContainsString('/tools', $content);
        self::assertStringNotContainsString('/narzedzia', $content);
    }
}
