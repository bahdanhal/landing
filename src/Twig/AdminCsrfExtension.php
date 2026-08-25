<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdminCsrfExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $secret,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_csrf_token', $this->generateCsrfToken(...)),
            new TwigFunction('csrf_token', $this->generateCsrfToken(...)),
        ];
    }

    public function generateCsrfToken(string $tokenId): string
    {
        return hash_hmac('sha256', 'csrf:' . $tokenId, $this->secret);
    }
}
