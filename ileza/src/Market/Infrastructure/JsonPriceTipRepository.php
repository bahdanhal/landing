<?php

declare(strict_types=1);

namespace App\Market\Infrastructure;

use App\Market\Domain\PriceTip;
use App\Market\Domain\PriceTipRepository;

/**
 * @deprecated Use App\Market\Infrastructure\DoctrinePriceTipRepository for production runtime persistence.
 *             Retained for data migration and lightweight test fixtures.
 */
final readonly class JsonPriceTipRepository implements PriceTipRepository
{
    private const RETENTION_DAYS = 90;

    public function __construct(
        private string $directory,
        private string $secret,
    ) {
    }

    public function submit(string $productSlug, string $listingUrl, string $email, string $ipAddress): PriceTip
    {
        $submittedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $tip = new PriceTip(
            $productSlug,
            $this->normalizeUrl($listingUrl),
            strtolower(trim($email)),
            substr(hash_hmac('sha256', $ipAddress, $this->secret), 0, 20),
            $submittedAt,
            $submittedAt->modify(sprintf('+%d days', self::RETENTION_DAYS)),
        );

        $directory = $this->tipDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to store the price tip.');
        }
        $path = sprintf('%s/%s-%s.json', $directory, $submittedAt->format('YmdHis'), bin2hex(random_bytes(6)));
        $json = json_encode($tip->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to store the price tip.');
        }
        @chmod($path, 0660);
        $this->pruneExpired();

        return $tip;
    }

    /** @return list<PriceTip> */
    public function all(): array
    {
        $this->pruneExpired();
        $tips = [];
        foreach (glob($this->tipDirectory() . '/*.json') ?: [] as $path) {
            try {
                $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
                if (is_array($data)) {
                    $tips[] = PriceTip::fromArray($data);
                }
            } catch (\Throwable) {
                continue;
            }
        }
        usort($tips, static fn (PriceTip $left, PriceTip $right): int => $right->submittedAt <=> $left->submittedAt);

        return $tips;
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048) {
            throw new \InvalidArgumentException('Enter a valid public listing URL.');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('Enter a valid public listing URL.');
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('Only public HTTP and HTTPS listing URLs are accepted.');
        }
        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if ($host === '' || $host === 'localhost' || !str_contains($host, '.')) {
            throw new \InvalidArgumentException('Enter a public marketplace URL.');
        }
        if (
            filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
        ) {
            throw new \InvalidArgumentException('Private and reserved network URLs are not accepted.');
        }
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = $parts['path'] ?? '/';

        return sprintf('%s://%s%s%s', $scheme, $host, $port, $path === '' ? '/' : $path);
    }

    public function pruneExpired(?\DateTimeImmutable $now = null): int
    {
        $reference = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $pruned = 0;
        foreach (glob($this->tipDirectory() . '/*.json') ?: [] as $path) {
            try {
                $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($data) || new \DateTimeImmutable((string) ($data['expires_at'] ?? '1970-01-01')) <= $reference) {
                    if (@unlink($path)) {
                        ++$pruned;
                    }
                }
            } catch (\Throwable) {
                if (@unlink($path)) {
                    ++$pruned;
                }
            }
        }

        return $pruned;
    }

    private function tipDirectory(): string
    {
        return rtrim($this->directory, '/') . '/price-tips';
    }
}
