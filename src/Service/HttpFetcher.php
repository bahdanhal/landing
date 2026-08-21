<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpFetcher
{
    private const USER_AGENT = 'BahdanToolbox/1.0 (+https://bahdan-hal.ovh/)';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UrlGuard $urlGuard,
        private readonly int $timeoutSeconds,
        private readonly int $maxBodyBytes,
    ) {
    }

    /** @return array{requested_url:string,final_url:string,status:int,headers:array,body:string,content_type:string,duration_ms:int,redirects:list<array{url:string,status:int,location:?string}>,error:?string} */
    public function fetch(string $url, int $maxRedirects = 8): array
    {
        $started = hrtime(true);
        $requestedUrl = $url;
        $redirects = [];

        try {
            for ($hop = 0; $hop <= $maxRedirects; ++$hop) {
                $resolvedIp = $this->urlGuard->assertSafe($url);
                $response = $this->httpClient->request('GET', $url, $this->options($url, $resolvedIp));

                $status = $response->getStatusCode();
                $headers = $response->getHeaders(false);
                $location = $headers['location'][0] ?? null;

                if ($status >= 300 && $status < 400 && is_string($location)) {
                    $redirects[] = ['url' => $url, 'status' => $status, 'location' => $location];
                    $url = $this->resolveUrl($url, $location);
                    continue;
                }

                $body = $response->getContent(false);
                $contentType = strtolower($headers['content-type'][0] ?? '');

                return [
                    'requested_url' => $requestedUrl,
                    'final_url' => $url,
                    'status' => $status,
                    'headers' => $headers,
                    'body' => $body,
                    'content_type' => $contentType,
                    'duration_ms' => $this->duration($started),
                    'redirects' => $redirects,
                    'error' => null,
                ];
            }

            throw new \RuntimeException('Too many redirects.');
        } catch (\Throwable $exception) {
            return [
                'requested_url' => $requestedUrl,
                'final_url' => $url,
                'status' => 0,
                'headers' => [],
                'body' => '',
                'content_type' => '',
                'duration_ms' => $this->duration($started),
                'redirects' => $redirects,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /** @return array<string, array> Results keyed by requested URL. */
    public function fetchMany(array $urls, int $maxRedirects = 8): array
    {
        $states = [];
        foreach (array_values(array_unique($urls)) as $url) {
            $states[$url] = [
                'requested_url' => $url,
                'current_url' => $url,
                'started' => hrtime(true),
                'redirects' => [],
                'done' => false,
            ];
        }

        for ($hop = 0; $hop <= $maxRedirects; ++$hop) {
            $responses = [];
            foreach ($states as $key => $state) {
                if ($state['done']) {
                    continue;
                }
                try {
                    $resolvedIp = $this->urlGuard->assertSafe($state['current_url']);
                    $responses[$key] = $this->httpClient->request('GET', $state['current_url'], $this->options($state['current_url'], $resolvedIp));
                } catch (\Throwable $exception) {
                    $states[$key]['result'] = $this->errorResult($state, $exception->getMessage());
                    $states[$key]['done'] = true;
                }
            }

            if ($responses === []) {
                break;
            }

            foreach ($responses as $key => $response) {
                try {
                    $status = $response->getStatusCode();
                    $headers = $response->getHeaders(false);
                    $location = $headers['location'][0] ?? null;
                    if ($status >= 300 && $status < 400 && is_string($location)) {
                        $states[$key]['redirects'][] = [
                            'url' => $states[$key]['current_url'],
                            'status' => $status,
                            'location' => $location,
                        ];
                        $states[$key]['current_url'] = $this->resolveUrl($states[$key]['current_url'], $location);
                        continue;
                    }

                    $states[$key]['result'] = [
                        'requested_url' => $states[$key]['requested_url'],
                        'final_url' => $states[$key]['current_url'],
                        'status' => $status,
                        'headers' => $headers,
                        'body' => $response->getContent(false),
                        'content_type' => strtolower($headers['content-type'][0] ?? ''),
                        'duration_ms' => $this->duration($states[$key]['started']),
                        'redirects' => $states[$key]['redirects'],
                        'error' => null,
                    ];
                    $states[$key]['done'] = true;
                } catch (\Throwable $exception) {
                    $states[$key]['result'] = $this->errorResult($states[$key], $exception->getMessage());
                    $states[$key]['done'] = true;
                }
            }
        }

        $results = [];
        foreach ($states as $key => $state) {
            $results[$key] = $state['result'] ?? $this->errorResult($state, 'Too many redirects.');
        }

        return $results;
    }

    public function resolveUrl(string $base, string $reference): string
    {
        if (preg_match('#^https?://#i', $reference)) {
            return $reference;
        }

        $baseParts = parse_url($base);
        if (!is_array($baseParts) || !isset($baseParts['scheme'], $baseParts['host'])) {
            return $reference;
        }

        $origin = $baseParts['scheme'].'://'.$baseParts['host'].(isset($baseParts['port']) ? ':'.$baseParts['port'] : '');
        if (str_starts_with($reference, '//')) {
            return $baseParts['scheme'].':'.$reference;
        }
        if (str_starts_with($reference, '/')) {
            return $origin.$reference;
        }
        if (str_starts_with($reference, '?')) {
            return $origin.($baseParts['path'] ?? '/').$reference;
        }

        $directory = preg_replace('#/[^/]*$#', '/', $baseParts['path'] ?? '/');
        $path = $directory.$reference;
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
            } else {
                $segments[] = $segment;
            }
        }

        return $origin.'/'.implode('/', $segments);
    }

    private function duration(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }

    private function options(string $url, string $resolvedIp): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return [
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,application/xml,text/xml;q=0.9,*/*;q=0.5',
            ],
            'max_redirects' => 0,
            'timeout' => $this->timeoutSeconds,
            'max_duration' => $this->timeoutSeconds,
            'verify_peer' => true,
            'verify_host' => true,
            'resolve' => [$host => $resolvedIp],
            'on_progress' => function (int $downloaded, int $downloadSize): void {
                if ($downloaded > $this->maxBodyBytes || $downloadSize > $this->maxBodyBytes) {
                    throw new \RuntimeException('Response body exceeded the configured size limit.');
                }
            },
        ];
    }

    private function errorResult(array $state, string $message): array
    {
        return [
            'requested_url' => $state['requested_url'],
            'final_url' => $state['current_url'],
            'status' => 0,
            'headers' => [],
            'body' => '',
            'content_type' => '',
            'duration_ms' => $this->duration($state['started']),
            'redirects' => $state['redirects'],
            'error' => $message,
        ];
    }
}
