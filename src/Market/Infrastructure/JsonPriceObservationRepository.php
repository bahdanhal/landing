<?php

declare(strict_types=1);

namespace App\Market\Infrastructure;

use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;

/**
 * @deprecated Use App\Market\Infrastructure\DoctrinePriceObservationRepository for production runtime persistence.
 *             Retained for data migration and lightweight test fixtures.
 */
final readonly class JsonPriceObservationRepository implements PriceObservationRepository
{
    public function __construct(private string $directory)
    {
    }

    public function save(PriceObservation $observation): void
    {
        $this->ensureDirectory();
        $path = $this->path($observation->productSlug);
        $handle = fopen($path . '.lock', 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new \RuntimeException('Could not lock market observation storage.');
        }

        try {
            $history = $this->read($path);
            $day = $observation->observedAt->format('Y-m-d');
            $history = array_values(array_filter(
                $history,
                static fn (PriceObservation $item) => $item->observedAt->format('Y-m-d') !== $day
            ));
            $history[] = $observation;
            usort($history, static fn (PriceObservation $a, PriceObservation $b) => $a->observedAt <=> $b->observedAt);
            $json = json_encode(
                array_map(static fn (PriceObservation $item) => $item->toArray(), $history),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
            $temporary = $path . '.tmp';
            if (file_put_contents($temporary, $json . "\n", LOCK_EX) === false || !rename($temporary, $path)) {
                throw new \RuntimeException('Could not persist market observation.');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return list<PriceObservation> */
    public function history(string $productSlug): array
    {
        $history = $this->read($this->path($productSlug));
        usort($history, static fn (PriceObservation $a, PriceObservation $b) => $b->observedAt <=> $a->observedAt);

        return $history;
    }

    public function latest(string $productSlug): ?PriceObservation
    {
        return $this->history($productSlug)[0] ?? null;
    }

    public function delete(string $productSlug, string $date): void
    {
        $this->ensureDirectory();
        $path = $this->path($productSlug);
        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path . '.lock', 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new \RuntimeException('Could not lock market observation storage.');
        }

        try {
            $history = $this->read($path);
            $history = array_values(array_filter(
                $history,
                static fn (PriceObservation $item) => $item->observedAt->format('Y-m-d') !== $date
            ));
            usort($history, static fn (PriceObservation $a, PriceObservation $b) => $a->observedAt <=> $b->observedAt);
            $json = json_encode(
                array_map(static fn (PriceObservation $item) => $item->toArray(), $history),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
            $temporary = $path . '.tmp';
            if (file_put_contents($temporary, $json . "\n", LOCK_EX) === false || !rename($temporary, $path)) {
                throw new \RuntimeException('Could not persist market observation.');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return list<PriceObservation> */
    private function read(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return array_values(array_map(PriceObservation::fromArray(...), is_array($data) ? $data : []));
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Could not create market observation storage.');
        }
    }

    private function path(string $productSlug): string
    {
        return rtrim($this->directory, '/') . '/' . $productSlug . '.json';
    }
}
