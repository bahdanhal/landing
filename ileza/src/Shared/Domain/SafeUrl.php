<?php

declare(strict_types=1);

namespace App\Shared\Domain;

final readonly class SafeUrl
{
    private string $normalizedUrl;

    public function __construct(string $rawUrl)
    {
        $trimmed = trim($rawUrl);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('URL cannot be empty.');
        }

        if (!str_starts_with($trimmed, 'http://') && !str_starts_with($trimmed, 'https://')) {
            $trimmed = 'https://' . $trimmed;
        }

        $parts = parse_url($trimmed);
        if ($parts === false || !isset($parts['host']) || trim($parts['host']) === '') {
            throw new \InvalidArgumentException('Invalid URL format.');
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }

        $this->normalizedUrl = $scheme . '://' . $host . $port . $path;
    }

    public static function fromString(string $url): self
    {
        return new self($url);
    }

    public function toString(): string
    {
        return $this->normalizedUrl;
    }

    public function __toString(): string
    {
        return $this->normalizedUrl;
    }

    public function getHost(): string
    {
        $parts = parse_url($this->normalizedUrl);
        return $parts['host'] ?? '';
    }

    public function getScheme(): string
    {
        $parts = parse_url($this->normalizedUrl);
        return $parts['scheme'] ?? 'https';
    }

    public function sanitizedForLogging(): string
    {
        return $this->normalizedUrl;
    }
}
