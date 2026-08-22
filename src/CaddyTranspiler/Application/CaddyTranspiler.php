<?php

declare(strict_types=1);

namespace App\CaddyTranspiler\Application;

use App\CaddyTranspiler\Domain\Engine\ApacheTranspiler;
use App\CaddyTranspiler\Domain\Engine\NginxTranspiler;
use App\CaddyTranspiler\Domain\Model\ServerType;
use App\CaddyTranspiler\Domain\Model\TranspileResult;

final readonly class CaddyTranspiler
{
    private NginxTranspiler $nginxTranspiler;
    private ApacheTranspiler $apacheTranspiler;

    public function __construct(
        ?NginxTranspiler $nginxTranspiler = null,
        ?ApacheTranspiler $apacheTranspiler = null,
    ) {
        $this->nginxTranspiler = $nginxTranspiler ?? new NginxTranspiler();
        $this->apacheTranspiler = $apacheTranspiler ?? new ApacheTranspiler();
    }

    /**
     * Transpile raw server configuration into Caddyfile.
     */
    public function transpile(string $configContent, ServerType|string|null $serverType = null): TranspileResult
    {
        $detectedType = $this->resolveServerType($configContent, $serverType);

        return match ($detectedType) {
            ServerType::Apache => $this->apacheTranspiler->transpile($configContent),
            ServerType::Nginx => $this->nginxTranspiler->transpile($configContent),
        };
    }

    private function resolveServerType(string $configContent, ServerType|string|null $serverType): ServerType
    {
        if ($serverType instanceof ServerType) {
            return $serverType;
        }

        if (is_string($serverType) && $serverType !== '') {
            return ServerType::fromString($serverType);
        }

        // Auto-detect from content if not explicitly specified
        if (
            str_contains($configContent, 'RewriteEngine') ||
            str_contains($configContent, 'RewriteRule') ||
            str_contains($configContent, '<VirtualHost') ||
            str_contains($configContent, '<IfModule') ||
            str_contains($configContent, 'DocumentRoot') ||
            str_contains($configContent, 'ProxyPass')
        ) {
            return ServerType::Apache;
        }

        return ServerType::Nginx;
    }
}
