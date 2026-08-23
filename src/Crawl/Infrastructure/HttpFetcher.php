<?php

declare(strict_types=1);

namespace App\Crawl\Infrastructure;

use App\Shared\Infrastructure\Http\SafeHttpFetcher;
use App\Shared\Infrastructure\Http\UrlGuard as SharedUrlGuard;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @deprecated Use App\Shared\Infrastructure\Http\SafeHttpFetcher instead.
 */
class HttpFetcher extends SafeHttpFetcher
{
    public function __construct(
        HttpClientInterface $httpClient,
        SharedUrlGuard|UrlGuard $urlGuard,
        int $timeoutSeconds,
        int $maxBodyBytes,
    ) {
        $actualGuard = $urlGuard instanceof SharedUrlGuard ? $urlGuard : new SharedUrlGuard();
        parent::__construct($httpClient, $actualGuard, $timeoutSeconds, $maxBodyBytes);
    }
}
