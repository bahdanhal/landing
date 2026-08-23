<?php

declare(strict_types=1);

namespace App\Crawl\Infrastructure;

use App\Shared\Infrastructure\Http\UrlGuard as SharedUrlGuard;

/**
 * @deprecated Use App\Shared\Infrastructure\Http\UrlGuard instead.
 */
final readonly class UrlGuard
{
    private SharedUrlGuard $inner;

    public function __construct()
    {
        $this->inner = new SharedUrlGuard();
    }

    public function normalize(string $input): string
    {
        return $this->inner->normalize($input);
    }

    public function assertSafe(string $url): string
    {
        return $this->inner->assertSafe($url);
    }
}
