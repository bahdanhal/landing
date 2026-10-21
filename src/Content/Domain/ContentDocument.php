<?php

declare(strict_types=1);

namespace App\Content\Domain;

/**
 * Validates and normalizes a whole website content document:
 * {collections: {experience: [...], ...}, texts: {"about.heading": {en, pl}, ...}}.
 */
final class ContentDocument
{
    /**
     * @param array<array-key, mixed> $input
     * @param array<string, array{en: string, pl: string}> $textDefaults
     *
     * @return array{collections: array<string, list<array<string, mixed>>>, texts: array<string, array{en: string, pl: string}>}
     */
    public static function normalize(array $input, array $textDefaults): array
    {
        $collections = is_array($input['collections'] ?? null) ? $input['collections'] : [];
        $result = ['collections' => [], 'texts' => []];
        foreach (ContentSchema::collections() as $name => $definition) {
            $items = $collections[$name] ?? [];
            if (!is_array($items) || !array_is_list($items)) {
                throw new ContentValidationException($definition['label'] . ': expected a list.');
            }
            if (count($items) > ContentSchema::MAX_ITEMS) {
                throw new ContentValidationException(sprintf('%s: at most %d items.', $definition['label'], ContentSchema::MAX_ITEMS));
            }
            $ids = [];
            $result['collections'][$name] = [];
            foreach ($items as $index => $item) {
                $where = sprintf('%s › %s %d', $definition['label'], $definition['item'], $index + 1);
                if (!is_array($item)) {
                    throw new ContentValidationException($where . ': invalid item.');
                }
                $result['collections'][$name][] = self::item($item, $definition['fields'], $where, $ids);
            }
        }
        $unknown = array_diff_key($collections, ContentSchema::collections());
        if ($unknown !== []) {
            throw new ContentValidationException('Unknown content list: ' . implode(', ', array_keys($unknown)) . '.');
        }

        $texts = $input['texts'] ?? [];
        if (!is_array($texts)) {
            throw new ContentValidationException('Page texts: invalid format.');
        }
        foreach ($texts as $key => $value) {
            $key = (string) $key;
            if (!isset($textDefaults[$key])) {
                throw new ContentValidationException('Unknown page text: ' . $key . '.');
            }
            $shared = in_array($key, ContentSchema::SHARED_TEXTS, true);
            $pair = self::pair($value, 'Page text “' . $key . '”', $shared);
            if ($shared) {
                self::assertUrl($pair['en'], 'Page text “' . $key . '”');
            }
            if ($pair !== $textDefaults[$key]) {
                $result['texts'][$key] = $pair;
            }
        }
        ksort($result['texts']);

        return $result;
    }

    /**
     * @param array<array-key, mixed> $item
     * @param array<string, array{type: string, label: string, shared?: bool}> $fields
     * @param array<string, true> $ids
     *
     * @return array<string, mixed>
     */
    private static function item(array $item, array $fields, string $where, array &$ids): array
    {
        $id = $item['id'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $id) || isset($ids[$id])) {
            throw new ContentValidationException($where . ': invalid or duplicate identifier.');
        }
        $ids[$id] = true;
        $result = ['id' => $id];
        foreach ($fields as $field => $settings) {
            $label = $where . ' › ' . $settings['label'];
            $value = $item[$field] ?? null;
            $result[$field] = match ($settings['type']) {
                'text', 'textarea' => ($settings['shared'] ?? false)
                    ? self::string($value, $label)
                    : self::pair($value, $label, false),
                'list' => self::list($value, $label),
                'url' => self::assertUrl(self::string($value, $label), $label),
                'image' => self::image($value, $label),
                'bool' => $value === true,
                default => throw new \LogicException('Unknown field type ' . $settings['type']),
            };
        }
        $unknown = array_diff_key($item, $fields, ['id' => true]);
        if ($unknown !== []) {
            throw new ContentValidationException($where . ': unknown fields ' . implode(', ', array_keys($unknown)) . '.');
        }

        return $result;
    }

    /** @return array{en: string, pl: string} */
    private static function pair(mixed $value, string $label, bool $shared): array
    {
        if (!is_array($value)) {
            throw new ContentValidationException($label . ': expected English and Polish text.');
        }
        $en = self::string($value['en'] ?? null, $label . ' (EN)');
        $pl = $shared ? $en : self::string($value['pl'] ?? null, $label . ' (PL)');

        return ['en' => $en, 'pl' => $pl];
    }

    /** @return list<array{en: string, pl: string}> */
    private static function list(mixed $value, string $label): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new ContentValidationException($label . ': expected a list.');
        }
        if (count($value) > ContentSchema::MAX_LIST_ENTRIES) {
            throw new ContentValidationException(sprintf('%s: at most %d entries.', $label, ContentSchema::MAX_LIST_ENTRIES));
        }
        $entries = [];
        foreach ($value as $index => $entry) {
            $pair = self::pair($entry, sprintf('%s › %d', $label, $index + 1), false);
            if ($pair['en'] !== '' || $pair['pl'] !== '') {
                $entries[] = $pair;
            }
        }

        return $entries;
    }

    private static function string(mixed $value, string $label): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
            throw new ContentValidationException($label . ': expected text.');
        }
        $value = trim(str_replace("\r\n", "\n", $value));
        if (mb_strlen($value) > ContentSchema::MAX_TEXT_LENGTH) {
            throw new ContentValidationException(sprintf('%s: at most %d characters.', $label, ContentSchema::MAX_TEXT_LENGTH));
        }

        return $value;
    }

    private static function assertUrl(string $value, string $label): string
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false || parse_url($value, PHP_URL_SCHEME) !== 'https') {
            throw new ContentValidationException($label . ': enter a full address starting with https://.');
        }

        return $value;
    }

    private static function image(mixed $value, string $label): string
    {
        if (!is_string($value) || !in_array($value, ContentSchema::IMAGES, true)) {
            throw new ContentValidationException($label . ': choose one of the available images.');
        }

        return $value;
    }
}
