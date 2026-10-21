<?php

declare(strict_types=1);

namespace App\Content\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;

final readonly class ContentStore
{
    private const DOCUMENT_ID = 'site';

    public function __construct(private Connection $connection, private LoggerInterface $logger)
    {
    }

    /**
     * Same as read(), but public pages keep working with the shipped content if the database is unavailable.
     *
     * @return array{draft: array<string, mixed>|null, published: array<string, mixed>|null, revision: int}
     */
    public function readForVisitors(): array
    {
        try {
            return $this->read();
        } catch (DbalException | \JsonException | \UnexpectedValueException $exception) {
            $this->logger->error('Website content unavailable, showing shipped defaults.', ['exception' => $exception]);

            return ['draft' => null, 'published' => null, 'revision' => 0];
        }
    }

    /** @return array{draft: array<string, mixed>|null, published: array<string, mixed>|null, revision: int} */
    public function read(): array
    {
        try {
            $row = $this->connection->fetchAssociative(
                'SELECT draft, published, revision FROM site_document WHERE id = ?',
                [self::DOCUMENT_ID],
            );
        } catch (TableNotFoundException) {
            // Keep public pages available while the migration is being applied.
            $row = false;
        }
        if ($row === false) {
            return ['draft' => null, 'published' => null, 'revision' => 0];
        }

        return [
            'draft' => $this->decode($row['draft']),
            'published' => $this->decode($row['published']),
            'revision' => (int) $row['revision'],
        ];
    }

    /**
     * Saves both versions if nobody else saved since $revision was read.
     *
     * @param array<string, mixed>|null $draft
     * @param array<string, mixed>|null $published
     */
    public function write(?array $draft, ?array $published, int $revision): int
    {
        return $this->connection->transactional(function () use ($draft, $published, $revision): int {
            $this->connection->executeStatement(
                'INSERT INTO site_document (id, draft, published, revision, updated_at) VALUES (?, NULL, NULL, 0, ?) '
                . 'ON CONFLICT (id) DO NOTHING',
                [self::DOCUMENT_ID, new \DateTimeImmutable()],
                [Types::STRING, Types::DATETIMETZ_IMMUTABLE],
            );
            $changed = $this->connection->executeStatement(
                'UPDATE site_document SET draft = ?, published = ?, revision = revision + 1, updated_at = ? '
                . 'WHERE id = ? AND revision = ?',
                [$draft, $published, new \DateTimeImmutable(), self::DOCUMENT_ID, $revision],
                [Types::JSON, Types::JSON, Types::DATETIMETZ_IMMUTABLE, Types::STRING, Types::INTEGER],
            );
            if ($changed !== 1) {
                throw new ContentConflictException();
            }

            return $revision + 1;
        });
    }

    /** @return array<string, mixed>|null */
    private function decode(mixed $json): ?array
    {
        if ($json === null) {
            return null;
        }
        $value = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new \UnexpectedValueException('Invalid stored website content.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
