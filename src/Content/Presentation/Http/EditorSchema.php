<?php

declare(strict_types=1);

namespace App\Content\Presentation\Http;

use App\Content\Domain\ContentSchema;

/** Builds the description of content types that the editor UI renders itself from. */
final class EditorSchema
{
    private const TEXT_GROUP_ORDER = [
        'home', 'obd', 'story', 'products', 'ai-notes', 'next', 'home-seo',
        'resume', 'resume-seo', 'services', 'services-seo', 'shared', 'contact', 'not-found',
    ];

    private const LABELS = [
        'meta.title' => 'Page title (browser tab and Google)',
        'meta.description' => 'Description (Google snippet)',
        'intro' => 'Introduction',
        'heading_emphasis' => 'Heading, highlighted part',
        'p1' => 'First paragraph',
        'p2' => 'Second paragraph',
        'cta_resume' => 'Button: résumé',
        'cta_services' => 'Button: services',
        'cta_contact' => 'Button: contact',
        'cta_consultation' => 'Button: consultation',
        'cta_process' => 'Button: process',
    ];

    /**
     * @param array<string, array{en: string, pl: string}> $textDefaults
     *
     * @return array<string, mixed>
     */
    public static function build(array $textDefaults): array
    {
        $groups = [];
        foreach (ContentSchema::textGroups() as $id => $group) {
            $groups[$id] = ['id' => $id, 'label' => $group['label'], 'group' => $group['group'], 'fields' => []];
        }
        foreach ($textDefaults as $key => $default) {
            $groupId = ContentSchema::textGroupFor($key);
            $groups[$groupId]['fields'][] = [
                'key' => $key,
                'label' => self::label($key, ContentSchema::textGroups()[$groupId]['prefixes']),
                'shared' => in_array($key, ContentSchema::SHARED_TEXTS, true),
                'multiline' => max(mb_strlen($default['en']), mb_strlen($default['pl'])) > 80,
            ];
        }

        $collections = [];
        foreach (ContentSchema::collections() as $name => $definition) {
            $fields = [];
            foreach ($definition['fields'] as $field => $settings) {
                $fields[] = ['name' => $field] + $settings;
            }
            $collections[] = ['name' => $name, 'fields' => $fields] + array_diff_key($definition, ['fields' => true]);
        }

        $order = array_flip(self::TEXT_GROUP_ORDER);
        uksort($groups, static fn (string $a, string $b): int => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        return [
            'collections' => $collections,
            'textGroups' => array_values(array_filter($groups, static fn (array $group): bool => $group['fields'] !== [])),
            'images' => ContentSchema::IMAGES,
            'limits' => ['items' => ContentSchema::MAX_ITEMS, 'entries' => ContentSchema::MAX_LIST_ENTRIES, 'text' => ContentSchema::MAX_TEXT_LENGTH],
        ];
    }

    /** @param list<string> $prefixes */
    private static function label(string $key, array $prefixes): string
    {
        $rest = $key;
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($key, $prefix)) {
                $rest = substr($key, strlen($prefix));
                break;
            }
        }
        foreach (self::LABELS as $suffix => $label) {
            if ($rest === $suffix || str_ends_with($key, '.' . $suffix)) {
                return $label;
            }
        }
        $label = str_replace(['_', '.'], [' ', ' · '], $rest);

        return mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    }
}
