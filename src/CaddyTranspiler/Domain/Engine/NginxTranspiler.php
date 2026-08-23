<?php

declare(strict_types=1);

namespace App\CaddyTranspiler\Domain\Engine;

use App\CaddyTranspiler\Domain\Model\MigrationAdvisory;
use App\CaddyTranspiler\Domain\Model\ServerType;
use App\CaddyTranspiler\Domain\Model\TranspileResult;

final class NginxTranspiler
{
    /**
     * Transpile Nginx configuration content into Caddyfile format.
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
        $customBlocks = [];
        $basicAuth = false;

        $hasSslDirective = false;
        $hasWebSocketHeaders = false;
        $hasCompression = false;

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Clean line of trailing braces/semicolon for parsing
            $clean = trim($line, " \t\n\r\0\x0B;{}");

            // 1. Server name / address
            if (preg_match('/(?:^|[\s{;])server_name\s+([^;{}]+)/i', $line, $matches)) {
                $names = preg_split('/\s+/', trim($matches[1])) ?: [];
                foreach ($names as $name) {
                    $name = trim($name);
                    if ($name !== '_' && $name !== 'localhost' && !str_starts_with($name, '$')) {
                        $siteAddresses[] = $name;
                    } elseif ($name === 'localhost') {
                        $siteAddresses[] = 'localhost';
                    }
                }
                $detectedFeatures[] = 'Server names (' . implode(', ', $siteAddresses) . ')';
                continue;
            }

            // 2. Root path
            if (preg_match('/(?:^|[\s{;])root\s+([^;{}]+)/i', $line, $matches)) {
                $rootPath = trim($matches[1]);
                $fileServerNeeded = true;
                $detectedFeatures[] = 'Document root (' . $rootPath . ')';
                continue;
            }

            // 3. SSL directives (Automatic in Caddy)
            if (preg_match('/(?:^|[\s{;])ssl_(certificate|certificate_key|protocols|ciphers|prefer_server_ciphers|session_)/i', $line)) {
                $hasSslDirective = true;
                $omittedDirectives[] = $line;
                continue;
            }

            // 4. Proxy Pass
            if (preg_match('/(?:^|[\s{;])proxy_pass\s+(https?:\/\/[^\s;{}]+)/i', $line, $matches)) {
                $upstream = trim($matches[1]);
                // Strip http:// if standard
                $upstreamClean = preg_replace('/^https?:\/\//', '', $upstream) ?? $upstream;
                $reverseProxies[] = [
                    'path' => null,
                    'upstream' => $upstreamClean,
                ];
                $detectedFeatures[] = 'Reverse Proxy (' . $upstreamClean . ')';
                continue;
            }

            // 5. Proxy Headers (WebSockets & X-Forwarded-*)
            if (preg_match('/(?:^|[\s{;])proxy_set_header\s+([^\s]+)\s+([^;{}]+)/i', $line, $matches)) {
                $headerName = strtolower(trim($matches[1]));
                $headerVal = trim($matches[2]);

                if (in_array($headerName, ['upgrade', 'connection'], true) || str_contains($headerVal, 'upgrade')) {
                    $hasWebSocketHeaders = true;
                    $omittedDirectives[] = $line;
                } elseif (in_array($headerName, ['host', 'x-real-ip', 'x-forwarded-for', 'x-forwarded-proto'], true)) {
                    $omittedDirectives[] = $line;
                } else {
                    $headers[] = 'header_up ' . $matches[1] . ' ' . $headerVal;
                }
                continue;
            }

            // 6. PHP-FPM / FastCGI
            if (preg_match('/(?:^|[\s{;])fastcgi_pass\s+([^;{}]+)/i', $line, $matches)) {
                $target = trim($matches[1]);
                if (str_starts_with($target, 'unix:')) {
                    $sock = substr($target, 5);
                    $phpFastcgi = 'unix//' . ltrim($sock, '/');
                } else {
                    $phpFastcgi = $target;
                }
                $fileServerNeeded = true;
                $detectedFeatures[] = 'PHP-FPM Router (' . $phpFastcgi . ')';
                continue;
            }

            // 7. Try files
            if (preg_match('/(?:^|[\s{;])try_files\s+([^;{}]+)/i', $line, $matches)) {
                $args = preg_split('/\s+/', trim($matches[1])) ?: [];
                $tryFiles = $args;
                $fileServerNeeded = true;
                continue;
            }

            // 8. Add Header
            if (preg_match('/(?:^|[\s{;])add_header\s+([^\s]+)\s+([^;{}]+?)(?:\s+always)?(?:;|$)/i', $line, $matches)) {
                $hName = trim($matches[1]);
                $hVal = trim($matches[2]);
                $headers[] = 'header ' . $hName . ' ' . $hVal;
                $detectedFeatures[] = 'Security/Response Header (' . $hName . ')';
                continue;
            }

            // 9. Client max body size
            if (preg_match('/(?:^|[\s{;])client_max_body_size\s+(\d+[kKmMgG]?)/i', $line, $matches)) {
                $rawSize = trim($matches[1]);
                $size = strtoupper($rawSize);
                if (str_ends_with($size, 'M')) {
                    $size .= 'B';
                } elseif (str_ends_with($size, 'K')) {
                    $size .= 'B';
                } elseif (str_ends_with($size, 'G')) {
                    $size .= 'B';
                }
                $requestBodyLimit = $size;
                $detectedFeatures[] = 'Max Body Size (' . $size . ')';
                continue;
            }

            // 10. Gzip / Compression
            if (preg_match('/(?:^|[\s{;])gzip\s+on/i', $line)) {
                $hasCompression = true;
                $encodeDirective = 'encode zstd gzip';
                $detectedFeatures[] = 'Zstandard + Gzip Compression';
                continue;
            }

            // 11. Rewrite / Redirect
            if (preg_match('/^rewrite\s+\^?([^\s]+)\s+([^\s]+)(?:\s+(permanent|redirect))?$/i', $clean, $matches)) {
                $from = $matches[1];
                $to = $matches[2];
                $type = isset($matches[3]) && strtolower($matches[3]) === 'permanent' ? 'permanent' : 'temporary';

                // Transform regex capture groups like (.*) and $1 into Caddy format
                $caddyFrom = str_replace('(.*)', '*', $from);
                $caddyFrom = rtrim($caddyFrom, '$');
                if (!str_starts_with($caddyFrom, '/')) {
                    $caddyFrom = '/' . $caddyFrom;
                }

                $caddyTo = preg_replace('/\$1/', '{1}', $to) ?? $to;
                $caddyTo = preg_replace('/\$2/', '{2}', $caddyTo) ?? $caddyTo;

                $redirections[] = 'redir ' . $caddyFrom . ' ' . $caddyTo . ' ' . $type;
                $detectedFeatures[] = 'Redirect (' . $caddyFrom . ' → ' . $caddyTo . ')';
                continue;
            }

            // 12. Return redirect
            if (preg_match('/^return\s+(301|302|307|308)\s+(.+)$/i', $clean, $matches)) {
                $code = $matches[1];
                $target = trim($matches[2]);
                $target = str_replace(['$host', '$request_uri', '$uri'], ['{host}', '{uri}', '{uri}'], $target);
                $type = in_array($code, ['301', '308'], true) ? 'permanent' : 'temporary';
                $redirections[] = 'redir ' . $target . ' ' . $type;
                $detectedFeatures[] = 'Status ' . $code . ' Redirection';
                continue;
            }

            // 13. Basic Auth
            if (preg_match('/^auth_basic\s+/i', $clean)) {
                $basicAuth = true;
                $detectedFeatures[] = 'HTTP Basic Authentication';
                continue;
            }
        }

        // Build Advisories
        if ($hasSslDirective) {
            $advisories[] = new MigrationAdvisory(
                code: 'AUTO_HTTPS',
                severity: 'info',
                title: 'Automatic HTTPS Enabled',
                description: 'Caddy automatically manages SSL certificates via Let\'s Encrypt / ZeroSSL. Explicit certificate paths were removed as redundant.',
                suggestion: 'No manual certificate renewal cron jobs or certbot scripts required.'
            );
        }

        if ($hasWebSocketHeaders) {
            $advisories[] = new MigrationAdvisory(
                code: 'BUILTIN_WEBSOCKETS',
                severity: 'info',
                title: 'Native WebSocket Support',
                description: 'Caddy reverse_proxy handles WebSocket upgrade headers and connection states automatically out of the box.',
                suggestion: 'You do not need to configure Connection/Upgrade proxy headers manually.'
            );
        }

        if ($requestBodyLimit !== null) {
            $advisories[] = new MigrationAdvisory(
                code: 'MAX_BODY_SIZE',
                severity: 'warning',
                title: 'Custom Request Body Limit',
                description: 'Nginx client_max_body_size was translated to Caddy request_body max_size.',
                suggestion: "Configured: request_body { max_size {$requestBodyLimit} }"
            );
        }

        if ($phpFastcgi !== null) {
            $advisories[] = new MigrationAdvisory(
                code: 'FASTCGI_CONSOLIDATION',
                severity: 'tip',
                title: 'PHP-FPM Router Consolidated',
                description: 'Nginx try_files + fastcgi_pass and param includes are consolidated into a single php_fastcgi directive in Caddy.',
                suggestion: 'php_fastcgi handles routing to index.php and path sanitization automatically.'
            );
        }

        if ($hasCompression) {
            $advisories[] = new MigrationAdvisory(
                code: 'COMPRESSION_ZSTD',
                severity: 'tip',
                title: 'Modern Compression Enabled',
                description: 'Configured "encode zstd gzip" for optimal payload compression with Zstandard and Gzip fallback.',
                suggestion: 'Caddy dynamically compresses compatible MIME responses with zero extra configuration.'
            );
        }

        // Assemble Caddyfile Output
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
        } elseif ($tryFiles !== null) {
            // Check if SPA fallback
            $lastArg = end($tryFiles);
            if ($lastArg === '/index.html' || str_ends_with((string) $lastArg, 'index.html')) {
                $linesOut[] = "    try_files {path} /index.html";
            }
        }

        foreach ($reverseProxies as $proxy) {
            $linesOut[] = "    reverse_proxy {$proxy['upstream']}";
        }

        if ($fileServerNeeded) {
            $linesOut[] = "    file_server";
        }

        // If completely empty site body, provide sensible default
        if (count($linesOut) === 1) {
            $linesOut[] = "    # Reverse proxy or file server configuration";
            $linesOut[] = "    reverse_proxy 127.0.0.1:3000";
        }

        $linesOut[] = "}";

        return new TranspileResult(
            caddyfile: implode("\n", $linesOut),
            sourceType: ServerType::Nginx,
            advisories: $advisories,
            detectedFeatures: array_values(array_unique($detectedFeatures)),
            omittedDirectives: array_values(array_unique($omittedDirectives)),
            siteAddress: $siteHeader,
        );
    }
}
