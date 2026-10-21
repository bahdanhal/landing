<?php

declare(strict_types=1);

namespace App\Content\Application;

use App\Content\Domain\ContentDocument;
use App\Content\Infrastructure\ContentDefaults;
use App\Content\Infrastructure\ContentStore;

final readonly class EditContent
{
    public function __construct(private ContentStore $store, private ContentDefaults $defaults)
    {
    }

    /**
     * Everything the editor needs to start.
     *
     * @return array{draft: array<string, mixed>, published: array<string, mixed>, revision: int}
     */
    public function state(): array
    {
        $state = $this->store->read();
        $published = $state['published'] ?? $this->defaults->document();

        return ['draft' => $state['draft'] ?? $published, 'published' => $published, 'revision' => $state['revision']];
    }

    /**
     * @param array<array-key, mixed> $document
     *
     * @return array{draft: array<string, mixed>, published: array<string, mixed>, revision: int}
     */
    public function apply(string $action, array $document, int $revision): array
    {
        $state = $this->store->read();
        if ($action === 'discard') {
            $this->store->write(null, $state['published'], $revision);

            return $this->state();
        }
        if (!in_array($action, ['draft', 'publish'], true)) {
            throw new \InvalidArgumentException('Unknown editor action.');
        }
        $normalized = ContentDocument::normalize($document, $this->defaults->texts());
        if ($action === 'publish') {
            $this->store->write(null, $normalized, $revision);
        } else {
            $this->store->write($normalized, $state['published'], $revision);
        }

        return $this->state();
    }
}
