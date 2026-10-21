<?php

declare(strict_types=1);

namespace App\Content\Infrastructure;

use Symfony\Component\HttpFoundation\Request;

final readonly class ContentAdminAccess
{
    public function __construct(private string $secret)
    {
    }

    public function isAuthenticated(Request $request): bool
    {
        if (trim($this->secret) === '') {
            return false;
        }

        $cookie = (string) $request->cookies->get('portfolio_admin_auth', '');
        if (hash_equals(hash_hmac('sha256', 'portfolio_admin_authenticated', $this->secret), $cookie)) {
            return true;
        }

        $token = (string) $request->headers->get('X-Admin-Token', '');
        if ($token === '' && preg_match('/^Bearer\s+(\S+)$/i', (string) $request->headers->get('Authorization'), $matches)) {
            $token = $matches[1];
        }
        if (trim($token) === '') {
            return false;
        }
        foreach ([$_ENV['ADMIN_TOKEN'] ?? '', $_ENV['MCP_ADMIN_TOKEN'] ?? '', $this->secret] as $expected) {
            if (trim($expected) !== '' && hash_equals(trim($expected), trim($token))) {
                return true;
            }
        }

        return false;
    }

    public function validCsrf(string $id, string $token): bool
    {
        return trim($this->secret) !== '' && hash_equals(hash_hmac('sha256', 'csrf:' . $id, $this->secret), $token);
    }
}
