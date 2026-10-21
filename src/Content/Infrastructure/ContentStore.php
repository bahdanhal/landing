<?php

declare(strict_types=1);

namespace App\Content\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Types\Types;

final readonly class ContentStore
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return array{draft: array<string, string>, published: array<string, string>, revision: int} */
    public function read(string $locale): array
    {
        try {
            $row = $this->connection->fetchAssociative('SELECT draft, published, revision FROM site_content WHERE locale = ?', [$locale]);
        } catch (TableNotFoundException) {
            // Keep public pages available while the initial CMS migration is being applied.
            $row = false;
        }
        if ($row === false) {
            return ['draft' => [], 'published' => [], 'revision' => 0];
        }

        return [
            'draft' => $this->decode((string) $row['draft']),
            'published' => $this->decode((string) $row['published']),
            'revision' => (int) $row['revision'],
        ];
    }

    /** @param array<string, string> $draft
     *  @param array<string, string> $published
     */
    public function write(string $locale, array $draft, array $published, int $revision): void
    {
        $this->connection->transactional(function () use ($locale, $draft, $published, $revision): void {
            $this->connection->executeStatement(
                'INSERT INTO site_content (locale, draft, published, revision, updated_at) '
                . 'VALUES (?, ?, ?, 0, ?) ON CONFLICT (locale) DO NOTHING',
                [$locale, '{}', '{}', new \DateTimeImmutable()],
                [Types::STRING, Types::STRING, Types::STRING, Types::DATETIMETZ_IMMUTABLE],
            );
            $changed = $this->connection->executeStatement(
                'UPDATE site_content SET draft = ?, published = ?, revision = revision + 1, updated_at = ? '
                . 'WHERE locale = ? AND revision = ?',
                [$draft, $published, new \DateTimeImmutable(), $locale, $revision],
                [Types::JSON, Types::JSON, Types::DATETIMETZ_IMMUTABLE, Types::STRING, Types::INTEGER],
            );
            if ($changed !== 1) {
                throw new \DomainException('This content changed in another tab. Reload the editor before saving again.');
            }
        });
    }

    /** @return array<string, string> */
    private function decode(string $json): array
    {
        $values = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($values)) {
            throw new \UnexpectedValueException('Invalid stored website content.');
        }
        $result = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
