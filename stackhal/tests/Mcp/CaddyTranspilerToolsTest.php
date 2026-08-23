<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\CaddyTranspiler\Application\CaddyTranspiler;
use App\Mcp\CaddyTranspilerTools;
use PHPUnit\Framework\TestCase;

final class CaddyTranspilerToolsTest extends TestCase
{
    private CaddyTranspilerTools $tools;

    protected function setUp(): void
    {
        $this->tools = new CaddyTranspilerTools(new CaddyTranspiler());
    }

    public function testTranspileToCaddyfileMcpToolReturnsValidJson(): void
    {
        $config = "server {\n    server_name mcp.example.com;\n    location / { proxy_pass http://127.0.0.1:8080; }\n}";
        $raw = $this->tools->transpileToCaddyfile($config, 'nginx');

        self::assertJson($raw);
        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('completed', $data['status']);
        self::assertArrayHasKey('result', $data);
        self::assertStringContainsString('mcp.example.com {', $data['result']['caddyfile']);
        self::assertStringContainsString('reverse_proxy 127.0.0.1:8080', $data['result']['caddyfile']);
        self::assertSame('nginx', $data['result']['source_type']);
    }

    public function testTranspileToCaddyfileAutoDetectsApache(): void
    {
        $config = "RewriteEngine On\nRewriteRule ^docs/(.*)$ /v2/docs/$1 [R=301,L]";
        $raw = $this->tools->transpileToCaddyfile($config);

        self::assertJson($raw);
        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('completed', $data['status']);
        self::assertSame('apache', $data['result']['source_type']);
        self::assertStringContainsString('redir /docs/* /v2/docs/{1} permanent', $data['result']['caddyfile']);
    }
}
