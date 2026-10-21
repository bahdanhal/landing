<?php

declare(strict_types=1);

namespace App\Content\Application;

use App\Content\Infrastructure\ContentStore;

final readonly class EditContent
{
    public function __construct(private ContentCatalogue $catalogue, private ContentStore $store)
    {
    }

    /** @param array<array-key, mixed> $values */
    public function save(string $locale, string $section, array $values, int $revision, string $action): void
    {
        $defaults = $this->catalogue->fields($locale, $section);
        if (!in_array($action, ['draft', 'publish', 'discard'], true)) {
            throw new \InvalidArgumentException('Unknown editor action.');
        }
        $state = $this->store->read($locale);
        if ($revision !== $state['revision']) {
            throw new \DomainException('This content changed in another tab. Reload the editor before saving again.');
        }
        foreach ($defaults as $key => $default) {
            if ($action === 'discard') {
                if (array_key_exists($key, $state['published'])) {
                    $state['draft'][$key] = $state['published'][$key];
                } else {
                    unset($state['draft'][$key]);
                }
                continue;
            }
            $value = $values[$key] ?? null;
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 10000 || str_contains($value, "\0")) {
                throw new \InvalidArgumentException('Every field must contain text of no more than 10,000 characters.');
            }
            if (str_starts_with($key, 'links.') && (filter_var($value, FILTER_VALIDATE_URL) === false || parse_url($value, PHP_URL_SCHEME) !== 'https')) {
                throw new \InvalidArgumentException('Project links must be complete HTTPS addresses.');
            }
            if ($value === $default) {
                unset($state['draft'][$key]);
            } else {
                $state['draft'][$key] = $value;
            }
            if ($action === 'publish') {
                if ($value === $default) {
                    unset($state['published'][$key]);
                } else {
                    $state['published'][$key] = $value;
                }
            }
        }
        if (array_diff_key($values, $defaults) !== []) {
            throw new \InvalidArgumentException('The submitted form contains unknown fields.');
        }
        $this->store->write($locale, $state['draft'], $state['published'], $revision);
    }
}
