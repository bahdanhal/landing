<?php

declare(strict_types=1);

namespace App\Content\Application;

use Symfony\Component\Yaml\Yaml;

final readonly class ContentCatalogue
{
    public function __construct(private string $projectDir)
    {
    }

    /** @return array<string, array{title: string, prefix: string, page: string}> */
    public function sections(): array
    {
        return [
            'home' => ['title' => 'Home · Introduction', 'prefix' => 'about.', 'page' => 'landing'],
            'obd' => ['title' => 'Home · OBD Vault', 'prefix' => 'showcase.obd.', 'page' => 'landing'],
            'story' => ['title' => 'Home · Background', 'prefix' => 'about.story.', 'page' => 'landing'],
            'products' => ['title' => 'Home · Projects', 'prefix' => 'about.ecosystem.', 'page' => 'landing'],
            'ai-notes' => ['title' => 'Home · AI notes', 'prefix' => 'about.ai_dump.', 'page' => 'landing'],
            'photo' => ['title' => 'Home · Photo caption', 'prefix' => 'about.summit.', 'page' => 'landing'],
            'next' => ['title' => 'Home · Next steps', 'prefix' => 'about.gateways.', 'page' => 'landing'],
            'home-seo' => ['title' => 'Home · Search appearance', 'prefix' => 'about.meta.', 'page' => 'landing'],
            'resume' => ['title' => 'Résumé · Overview', 'prefix' => 'resume_page.', 'page' => 'resume'],
            'web24' => ['title' => 'Résumé · Web24', 'prefix' => 'resume_page.web24.', 'page' => 'resume'],
            'freedom' => ['title' => 'Résumé · Freedom Mobile', 'prefix' => 'resume_page.freedom.', 'page' => 'resume'],
            'gwo' => ['title' => 'Résumé · GWO', 'prefix' => 'resume_page.gwo.', 'page' => 'resume'],
            'manao' => ['title' => 'Résumé · Manao', 'prefix' => 'resume_page.manao.', 'page' => 'resume'],
            'consulting' => ['title' => 'Résumé · Consulting', 'prefix' => 'resume_page.consulting.', 'page' => 'resume'],
            'skills' => ['title' => 'Résumé · Skills', 'prefix' => 'resume_page.skills.', 'page' => 'resume'],
            'resume-seo' => ['title' => 'Résumé · Search appearance', 'prefix' => 'resume_page.meta.', 'page' => 'resume'],
            'services' => ['title' => 'Services · Overview', 'prefix' => 'services_page.', 'page' => 'services'],
            ...$this->serviceSections(),
            'faq' => ['title' => 'Services · Questions & answers', 'prefix' => 'services_page.faq.', 'page' => 'services'],
            'services-seo' => ['title' => 'Services · Search appearance', 'prefix' => 'services_page.meta.', 'page' => 'services'],
            'shared' => ['title' => 'Shared · Header & footer', 'prefix' => 'base.', 'page' => 'landing'],
            'contact' => ['title' => 'Shared · Contact form', 'prefix' => 'contact.', 'page' => 'landing'],
            'controls' => ['title' => 'Shared · Image controls', 'prefix' => 'showcase.', 'page' => 'landing'],
            'links' => ['title' => 'Shared · Project links', 'prefix' => 'links.', 'page' => 'landing'],
            'not-found' => ['title' => '404 · Missing page', 'prefix' => 'error404.', 'page' => 'landing'],
        ];
    }

    /** @return array<string, string> */
    public function defaults(string $locale): array
    {
        if (!in_array($locale, ['en', 'pl'], true)) {
            throw new \InvalidArgumentException('Choose English or Polish.');
        }
        $source = Yaml::parseFile($this->projectDir . '/translations/messages.' . $locale . '.yaml');
        if (!is_array($source)) {
            throw new \UnexpectedValueException('Invalid website text catalogue.');
        }

        return $this->flatten($source);
    }

    /** @return array<string, string> */
    public function fields(string $locale, string $section): array
    {
        $sections = $this->sections();
        if (!isset($sections[$section])) {
            throw new \InvalidArgumentException('Unknown website section.');
        }
        $fields = [];
        foreach ($this->defaults($locale) as $key => $value) {
            $match = '';
            $length = 0;
            foreach ($sections as $name => $settings) {
                if (str_starts_with($key, $settings['prefix']) && strlen($settings['prefix']) > $length) {
                    $match = $name;
                    $length = strlen($settings['prefix']);
                }
            }
            if ($match === $section) {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    public function label(string $key, string $section): string
    {
        $label = substr($key, strlen($this->sections()[$section]['prefix']));
        $label = preg_replace('/\bbullet(\d+)\b/', 'Point $1', $label) ?? $label;
        $label = preg_replace('/\bq(\d+)\b/', 'Question $1', $label) ?? $label;
        $label = preg_replace('/\ba(\d+)\b/', 'Answer $1', $label) ?? $label;

        return ucwords(str_replace(['_', '.'], [' ', ' · '], $label));
    }

    /** @return array<string, array{title: string, prefix: string, page: string}> */
    private function serviceSections(): array
    {
        $sections = [];
        foreach (['Websites', 'Online stores', 'Web applications', 'AI integrations', 'Automation', 'Audits'] as $i => $name) {
            $number = $i + 1;
            $sections['service-' . $number] = [
                'title' => 'Services · ' . $name,
                'prefix' => 'services_page.s' . $number . '.',
                'page' => 'services',
            ];
        }

        return $sections;
    }

    /** @param array<array-key, mixed> $source
     *  @return array<string, string>
     */
    private function flatten(array $source, string $prefix = ''): array
    {
        $result = [];
        foreach ($source as $key => $value) {
            $id = $prefix . $key;
            if (is_array($value)) {
                $result += $this->flatten($value, $id . '.');
            } elseif (is_string($value)) {
                $result[$id] = $value;
            }
        }

        return $result;
    }
}
