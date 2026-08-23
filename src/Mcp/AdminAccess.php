<?php

declare(strict_types=1);

namespace App\Mcp;

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class AdminAccess
{
    public function __construct(
        private RequestStack $requestStack,
        private string $token,
    ) {
    }

    public function isGranted(): bool
    {
        $expectedToken = trim($this->token);
        if ($expectedToken === '') {
            return false;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return false;
        }

        $authorization = (string) $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
            return false;
        }

        return hash_equals($expectedToken, $matches[1]);
    }
}
