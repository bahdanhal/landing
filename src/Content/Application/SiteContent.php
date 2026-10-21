<?php

declare(strict_types=1);

namespace App\Content\Application;

use App\Content\Domain\ContentSchema;
use App\Content\Infrastructure\ContentAdminAccess;
use App\Content\Infrastructure\ContentDefaults;
use App\Content\Infrastructure\ContentStore;
use Symfony\Component\HttpFoundation\Request;

/**
 * Gives pages the content they should show: the published version for visitors,
 * or the draft when a signed-in admin opens a page with ?_content_preview=1.
 */
final readonly class SiteContent
{
    private const DOCUMENT_ATTRIBUTE = '_site_document';
    public const PREVIEW_ATTRIBUTE = '_site_content_preview';

    public function __construct(
        private ContentStore $store,
        private ContentDefaults $defaults,
        private ContentAdminAccess $access,
    ) {
    }

    /** @return array<string, mixed> */
    public function forRequest(Request $request): array
    {
        $document = $request->attributes->get(self::DOCUMENT_ATTRIBUTE);
        if (is_array($document)) {
            /** @var array<string, mixed> $document */
            return $document;
        }
        $state = $this->store->readForVisitors();
        $preview = $request->query->get('_content_preview') === '1' && $this->access->isAuthenticated($request);
        $document = ($preview ? ($state['draft'] ?? $state['published']) : $state['published']) ?? $this->defaults->document();
        $request->attributes->set(self::DOCUMENT_ATTRIBUTE, $document);
        $request->attributes->set(self::PREVIEW_ATTRIBUTE, $preview);

        return $document;
    }

    /**
     * Resolves English/Polish pairs to plain strings for one language.
     *
     * @param array<string, mixed> $document
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function localize(array $document, string $locale): array
    {
        $locale = in_array($locale, ContentSchema::LOCALES, true) ? $locale : 'en';
        $collections = is_array($document['collections'] ?? null) ? $document['collections'] : [];
        $result = [];
        foreach (ContentSchema::collections() as $name => $definition) {
            $result[$name] = [];
            foreach (is_array($collections[$name] ?? null) ? $collections[$name] : [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $resolved = ['id' => $item['id'] ?? ''];
                foreach ($definition['fields'] as $field => $settings) {
                    $value = $item[$field] ?? null;
                    $resolved[$field] = match ($settings['type']) {
                        'list' => array_values(array_filter(array_map(
                            fn (mixed $entry): string => $this->pick($entry, $locale),
                            is_array($value) ? $value : [],
                        ), static fn (string $entry): bool => $entry !== '')),
                        'bool' => $value === true,
                        default => $this->pick($value, $locale),
                    };
                }
                $result[$name][] = $resolved;
            }
        }

        return $result;
    }

    private function pick(mixed $value, string $locale): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            $text = $value[$locale] ?? '';
            if (is_string($text) && $text !== '') {
                return $text;
            }
            $fallback = $value['en'] ?? '';

            return is_string($fallback) ? $fallback : '';
        }

        return '';
    }
}
