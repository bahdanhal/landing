<?php

declare(strict_types=1);

namespace App\Mcp;

use App\CaddyTranspiler\Application\CaddyTranspiler;
use App\CaddyTranspiler\Domain\Model\ServerType;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class CaddyTranspilerTools
{
    public function __construct(private CaddyTranspiler $transpiler)
    {
    }

    #[McpTool(
        name: 'transpile_to_caddyfile',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Transpile Nginx (nginx.conf, server blocks) or Apache (.htaccess, VirtualHost) web server configuration to clean, idiomatic Caddyfile with migration advisories.'
    )]
    public function transpileToCaddyfile(
        #[Schema(description: 'The raw web server configuration string (nginx.conf, server block, .htaccess, or VirtualHost) to transpile.')]
        string $config_content,
        #[Schema(description: 'Optional source server type: "nginx" or "apache". If omitted, auto-detected from content syntax.')]
        ?string $server_type = null,
    ): string {
        try {
            $type = $server_type !== null ? ServerType::fromString($server_type) : null;
            $result = $this->transpiler->transpile($config_content, $type);

            return $this->json([
                'status' => 'completed',
                'result' => $result->toArray(),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
