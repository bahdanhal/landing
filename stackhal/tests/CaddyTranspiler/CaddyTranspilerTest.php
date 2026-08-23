<?php

declare(strict_types=1);

namespace App\Tests\CaddyTranspiler;

use App\CaddyTranspiler\Application\CaddyTranspiler;
use App\CaddyTranspiler\Domain\Model\ServerType;
use PHPUnit\Framework\TestCase;

final class CaddyTranspilerTest extends TestCase
{
    private CaddyTranspiler $transpiler;

    protected function setUp(): void
    {
        $this->transpiler = new CaddyTranspiler();
    }

    public function testTranspileNginxReverseProxyWithHeadersAndSsl(): void
    {
        $nginx = <<<NGINX
server {
    listen 443 ssl;
    server_name api.example.com;
    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
NGINX;

        $result = $this->transpiler->transpile($nginx, ServerType::Nginx);

        self::assertStringContainsString('api.example.com {', $result->caddyfile);
        self::assertStringContainsString('reverse_proxy 127.0.0.1:3000', $result->caddyfile);
        self::assertStringNotContainsString('ssl_certificate', $result->caddyfile);
        self::assertStringNotContainsString('Upgrade', $result->caddyfile);

        $advisoryCodes = array_map(static fn ($a): string => $a->code, $result->advisories);
        self::assertContains('AUTO_HTTPS', $advisoryCodes);
        self::assertContains('BUILTIN_WEBSOCKETS', $advisoryCodes);
    }

    public function testTranspileNginxPhpFpmApplication(): void
    {
        $nginx = <<<NGINX
server {
    listen 80;
    server_name app.example.com;
    root /var/www/app/public;
    index index.php;

    client_max_body_size 50M;
    gzip on;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        include fastcgi_params;
    }
}
NGINX;

        $result = $this->transpiler->transpile($nginx, ServerType::Nginx);

        self::assertStringContainsString('app.example.com {', $result->caddyfile);
        self::assertStringContainsString('root * /var/www/app/public', $result->caddyfile);
        self::assertStringContainsString('php_fastcgi unix//var/run/php/php8.4-fpm.sock', $result->caddyfile);
        self::assertStringContainsString('file_server', $result->caddyfile);
        self::assertStringContainsString('max_size 50MB', $result->caddyfile);
        self::assertStringContainsString('encode zstd gzip', $result->caddyfile);

        $advisoryCodes = array_map(static fn ($a): string => $a->code, $result->advisories);
        self::assertContains('MAX_BODY_SIZE', $advisoryCodes);
        self::assertContains('FASTCGI_CONSOLIDATION', $advisoryCodes);
        self::assertContains('COMPRESSION_ZSTD', $advisoryCodes);
    }

    public function testTranspileApacheHtaccessWithRedirectsAndHeaders(): void
    {
        $apache = <<<APACHE
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^old-blog/(.*)$ /posts/$1 [R=301,L]
RewriteRule ^api/v1/(.*)$ /api/v2/$1 [R=302,L]
</IfModule>

Header set X-Frame-Options "DENY"
Header set X-Content-Type-Options "nosniff"
APACHE;

        $result = $this->transpiler->transpile($apache, ServerType::Apache);

        self::assertStringContainsString('redir /old-blog/* /posts/{1} permanent', $result->caddyfile);
        self::assertStringContainsString('redir /api/v1/* /api/v2/{1} temporary', $result->caddyfile);
        self::assertStringContainsString('header X-Frame-Options "DENY"', $result->caddyfile);
        self::assertStringContainsString('header X-Content-Type-Options "nosniff"', $result->caddyfile);
    }

    public function testTranspileApacheVirtualHostWithProxyPass(): void
    {
        $apache = <<<APACHE
<VirtualHost *:80>
    ServerName node.example.com
    ServerAlias api.node.example.com

    ProxyPass / http://127.0.0.1:3000/
    ProxyPassReverse / http://127.0.0.1:3000/
</VirtualHost>
APACHE;

        $result = $this->transpiler->transpile($apache);

        self::assertSame(ServerType::Apache, $result->sourceType);
        self::assertStringContainsString('node.example.com, api.node.example.com {', $result->caddyfile);
        self::assertStringContainsString('reverse_proxy 127.0.0.1:3000', $result->caddyfile);
    }

    public function testTranspileSpaFallback(): void
    {
        $nginx = <<<NGINX
server {
    server_name spa.example.com;
    root /var/www/dist;

    location / {
        try_files \$uri \$uri/ /index.html;
    }
}
NGINX;

        $result = $this->transpiler->transpile($nginx, ServerType::Nginx);

        self::assertStringContainsString('spa.example.com {', $result->caddyfile);
        self::assertStringContainsString('root * /var/www/dist', $result->caddyfile);
        self::assertStringContainsString('try_files {path} /index.html', $result->caddyfile);
        self::assertStringContainsString('file_server', $result->caddyfile);
    }
}
