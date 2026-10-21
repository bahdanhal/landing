<?php

declare(strict_types=1);

namespace App\Content\Infrastructure;

use App\Content\Domain\ContentSchema;
use Symfony\Component\Yaml\Yaml;

/**
 * The content shipped with the code: lists from content/site.yaml and single texts
 * from the translation files. Admin edits are stored on top of these.
 */
final class ContentDefaults
{
    /** @var array<string, mixed>|null */
    private ?array $collections = null;

    /** @var array<string, array{en: string, pl: string}>|null */
    private ?array $texts = null;

    public function __construct(private readonly string $projectDir)
    {
    }

    /** @return array{collections: array<string, mixed>, texts: array<string, array{en: string, pl: string}>} */
    public function document(): array
    {
        if ($this->collections === null) {
            $parsed = Yaml::parseFile($this->projectDir . '/content/site.yaml');
            if (!is_array($parsed)) {
                throw new \UnexpectedValueException('content/site.yaml must contain a mapping.');
            }
            /** @var array<string, mixed> $parsed */
            $this->collections = $parsed;
        }

        return ['collections' => $this->collections, 'texts' => []];
    }

    /** @return array<string, array{en: string, pl: string}> */
    public function texts(): array
    {
        if ($this->texts !== null) {
            return $this->texts;
        }
        $catalogues = [];
        foreach (ContentSchema::LOCALES as $locale) {
            $parsed = Yaml::parseFile($this->projectDir . '/translations/messages.' . $locale . '.yaml');
            $catalogues[$locale] = is_array($parsed) ? $this->flatten($parsed) : [];
        }
        $this->texts = [];
        foreach ($catalogues['en'] as $key => $english) {
            $this->texts[$key] = ['en' => $english, 'pl' => $catalogues['pl'][$key] ?? $english];
        }

        return $this->texts;
    }

    /**
     * @param array<array-key, mixed> $source
     *
     * @return array<string, string>
     */
    private function flatten(array $source, string $prefix = ''): array
    {
        $result = [];
        foreach ($source as $key => $value) {
            if (is_array($value)) {
                $result += $this->flatten($value, $prefix . $key . '.');
            } elseif (is_string($value)) {
                $result[$prefix . $key] = $value;
            }
        }

        return $result;
    }
}
