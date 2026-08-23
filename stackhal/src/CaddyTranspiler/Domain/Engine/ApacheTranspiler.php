<?php

declare(strict_types=1);

namespace App\CaddyTranspiler\Domain\Engine;

use App\CaddyTranspiler\Domain\Model\MigrationAdvisory;
use App\CaddyTranspiler\Domain\Model\ServerType;
use App\CaddyTranspiler\Domain\Model\TranspileResult;

final class ApacheTranspiler
{
    /**
     * Transpile Apache configuration (.htaccess or <VirtualHost>) into Caddyfile format.
     */
    public function transpile(string $config): TranspileResult
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($config)) ?: [];
        $advisories = [];
        $detectedFeatures = [];
        $omittedDirectives = [];

        $siteAddresses = [];
        $rootPath = null;
        $phpFastcgi = null;
        $fileServerNeeded = false;
        $encodeDirective = null;
        $headers = [];
        $reverseProxies = [];
        $redirections = [];
        $tryFiles = null;
        $requestBodyLimit = null;
        $basicAuth = false;

        $hasSslDirective = false;
        $isWpRewrite = false;
        $isSpaRewrite = false;

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // 1. ServerName / ServerAlias
            if (preg_match('/^ServerName\s+(.+)$/i', $line, $matches)) {
                $name = trim($matches[1]);
                if ($name !== '_' && $name !== 'localhost') {
                    $siteAddresses[] = $name;
                }
                $detectedFeatures[] = 'ServerName (' . $name . ')';
                continue;
            }

            if (preg_match('/^ServerAlias\s+(.+)$/i', $line, $matches)) {
                $aliases = preg_split('/\s+/', trim($matches[1])) ?: [];
                foreach ($aliases as $alias) {
                    $alias = trim($alias);
                    if ($alias !== '') {
                        $siteAddresses[] = $alias;
                    }
                }
                $detectedFeatures[] = 'ServerAlias (' . implode(', ', $aliases) . ')';
                continue;
            }

            // 2. DocumentRoot
            if (preg_match('/^DocumentRoot\s+["\']?([^"\']+)["\']?$/i', $line, $matches)) {
                $rootPath = trim($matches[1]);
                $fileServerNeeded = true;
                $detectedFeatures[] = 'DocumentRoot (' . $rootPath . ')';
                continue;
            }

            // 3. SSL Directives
            if (preg_match('/^(SSLEngine|SSLCertificateFile|SSLCertificateKeyFile|SSLCACertificateFile|SSLProtocol|SSLCipherSuite)/i', $line)) {
                $hasSslDirective = true;
                $omittedDirectives[] = $line;
                continue;
            }

            // 4. ProxyPass
            if (preg_match('/^ProxyPass\s+([^\s]+)\s+([^\s]+)$/i', $line, $matches)) {
                $path = trim($matches[1]);
                $upstream = trim($matches[2]);
                $upstreamClean = preg_replace('/^https?:\/\//', '', $upstream) ?? $upstream;
                $upstreamClean = rtrim($upstreamClean, '/');

                $reverseProxies[] = [
                    'path' => $path === '/' ? null : $path,
                    'upstream' => $upstreamClean,
                ];
                $detectedFeatures[] = 'ProxyPass (' . $upstreamClean . ')';
                continue;
            }

            // 5. ProxyPassReverse
            if (preg_match('/^ProxyPassReverse\s+/i', $line)) {
                $omittedDirectives[] = $line;
                continue;
            }

            // 6. Headers
            if (preg_match('/^Header\s+(?:always\s+)?set\s+([^\s]+)\s+["\']?([^"\']+)["\']?$/i', $line, $matches)) {
                $hName = trim($matches[1]);
                $hVal = trim($matches[2]);
                $headers[] = 'header ' . $hName . ' "' . $hVal . '"';
                $detectedFeatures[] = 'Header (' . $hName . ')';
                continue;
            }

            // 7. LimitRequestBody
            if (preg_match('/^LimitRequestBody\s+(\d+)$/i', $line, $matches)) {
                $bytes = (int) $matches[1];
                $mb = round($bytes / (1024 * 1024), 1);
                $requestBodyLimit = ($mb == (int) $mb ? (int) $mb : $mb) . 'MB';
                $detectedFeatures[] = 'LimitRequestBody (' . $requestBodyLimit . ')';
                continue;
            }

            // 8. Deflate / Compression
            if (preg_match('/(DEFLATE|mod_deflate)/i', $line)) {
                $encodeDirective = 'encode zstd gzip';
                $detectedFeatures[] = 'Zstandard + Gzip Compression';
                continue;
            }

            // 9. RewriteRule (Redirects and Front Controller)
            if (preg_match('/^RewriteRule\s+([^\s]+)\s+([^\s]+)(?:\s+\[(.*)\])?$/i', $line, $matches)) {
                $pattern = trim($matches[1]);
                $target = trim($matches[2]);
                $flags = isset($matches[3]) ? strtoupper(trim($matches[3])) : '';

                // Detect WordPress pattern (. /index.php)
                if (($pattern === '.' || $pattern === '^.*$') && str_contains($target, 'index.php')) {
                    $isWpRewrite = true;
                    $phpFastcgi = 'unix//run/php/php-fpm.sock';
                    $fileServerNeeded = true;
                    $detectedFeatures[] = 'PHP Front Controller / WordPress Rewrite';
                    continue;
                }

                // Detect SPA pattern (. /index.html)
                if (($pattern === '.' || $pattern === '^.*$') && str_contains($target, 'index.html')) {
                    $isSpaRewrite = true;
                    $tryFiles = ['{path}', '/index.html'];
                    $fileServerNeeded = true;
                    $detectedFeatures[] = 'SPA HTML5 History Fallback';
                    continue;
                }

                // Check for redirect flags R=301 or R=302
                if (str_contains($flags, 'R=301') || str_contains($flags, 'R=308')) {
                    $type = 'permanent';
                } elseif (str_contains($flags, 'R=302') || str_contains($flags, 'R')) {
                    $type = 'temporary';
                } else {
                    $type = null;
                }

                if ($type !== null) {
                    $caddyFrom = $pattern;
                    $caddyFrom = ltrim($caddyFrom, '^');
                    $caddyFrom = rtrim($caddyFrom, '$');
                    $caddyFrom = str_replace('(.*)', '*', $caddyFrom);
                    if (!str_starts_with($caddyFrom, '/')) {
                        $caddyFrom = '/' . $caddyFrom;
                    }

                    $caddyTo = preg_replace('/\$1/', '{1}', $target) ?? $target;
                    $caddyTo = preg_replace('/\$2/', '{2}', $caddyTo) ?? $caddyTo;

                    $redirections[] = 'redir ' . $caddyFrom . ' ' . $caddyTo . ' ' . $type;
                    $detectedFeatures[] = 'RewriteRule Redirect (' . $caddyFrom . ' → ' . $caddyTo . ')';
                    continue;
                }
            }

            // 10. Basic Auth
            if (preg_match('/^AuthType\s+Basic/i', $line) || preg_match('/^Require\s+valid-user/i', $line)) {
                $basicAuth = true;
                $detectedFeatures[] = 'HTTP Basic Authentication';
                continue;
            }
        }

        // Advisories
        if ($hasSslDirective) {
            $advisories[] = new MigrationAdvisory(
                code: 'AUTO_HTTPS',
                severity: 'info',
                title: 'Automatic HTTPS Enabled',
                description: 'Apache SSLCertificateFile and SSLEngine were removed. Caddy automatically manages certificates via Let\'s Encrypt / ZeroSSL.',
                suggestion: 'Zero SSL configuration needed in Caddyfile.'
            );
        }

        if ($requestBodyLimit !== null) {
            $advisories[] = new MigrationAdvisory(
                code: 'MAX_BODY_SIZE',
                severity: 'warning',
                title: 'LimitRequestBody Translated',
                description: 'Apache LimitRequestBody was translated into Caddy request_body max_size.',
                suggestion: "Configured: request_body { max_size {$requestBodyLimit} }"
            );
        }

        if ($isWpRewrite) {
            $advisories[] = new MigrationAdvisory(
                code: 'FASTCGI_CONSOLIDATION',
                severity: 'tip',
                title: 'Front-Controller / WordPress Simplified',
                description: 'Apache mod_rewrite front-controller rules were transformed into idiomatic php_fastcgi and file_server.',
                suggestion: 'Adjust php_fastcgi socket path if your PHP-FPM pool is on a different address.'
            );
        }

        // Output formatting
        $siteHeader = empty($siteAddresses) ? 'example.com' : implode(', ', array_unique($siteAddresses));
        $linesOut = [];
        $linesOut[] = "{$siteHeader} {";

        if ($encodeDirective !== null) {
            $linesOut[] = "    {$encodeDirective}";
        }

        if ($rootPath !== null) {
            $linesOut[] = "    root * {$rootPath}";
        }

        if ($requestBodyLimit !== null) {
            $linesOut[] = "    request_body {";
            $linesOut[] = "        max_size {$requestBodyLimit}";
            $linesOut[] = "    }";
        }

        foreach ($headers as $header) {
            $linesOut[] = "    {$header}";
        }

        foreach ($redirections as $redir) {
            $linesOut[] = "    {$redir}";
        }

        if ($basicAuth) {
            $linesOut[] = "    basic_auth {";
            $linesOut[] = "        # Generate hash with: caddy hash-password";
            $linesOut[] = "        username \$2a$14\$...";
            $linesOut[] = "    }";
        }

        if ($phpFastcgi !== null) {
            $linesOut[] = "    php_fastcgi {$phpFastcgi}";
        } elseif ($isSpaRewrite && $tryFiles !== null) {
            $linesOut[] = "    try_files {path} /index.html";
        }

        foreach ($reverseProxies as $proxy) {
            $prefix = $proxy['path'] ? $proxy['path'] . ' ' : '';
            $linesOut[] = "    reverse_proxy {$prefix}{$proxy['upstream']}";
        }

        if ($fileServerNeeded || ($rootPath !== null && empty($reverseProxies))) {
            $linesOut[] = "    file_server";
        }

        // If completely empty site body, provide sensible default
        if (count($linesOut) === 1) {
            $linesOut[] = "    # Reverse proxy or file server configuration";
            $linesOut[] = "    file_server";
        }

        $linesOut[] = "}";

        return new TranspileResult(
            caddyfile: implode("\n", $linesOut),
            sourceType: ServerType::Apache,
            advisories: $advisories,
            detectedFeatures: array_values(array_unique($detectedFeatures)),
            omittedDirectives: array_values(array_unique($omittedDirectives)),
            siteAddress: $siteHeader,
        );
    }
}
