<?php

declare(strict_types=1);

namespace App\Content\Domain;

/**
 * Describes every editable content type: repeatable lists (jobs, services, FAQ...)
 * and grouped single texts. The editor UI and the validator are both driven by it.
 *
 * Field types:
 *  - text / textarea: translated (English + Polish) unless 'shared' is true
 *  - list: ordered translated entries, for example the highlights of a job
 *  - url: one HTTPS address for both languages
 *  - image: one image path chosen from IMAGES
 *  - bool: a switch
 */
final class ContentSchema
{
    public const LOCALES = ['en', 'pl'];

    public const IMAGES = [
        '/images/projects/ileza-current.png',
        '/images/projects/ileza-home.jpg',
        '/images/projects/stackhal-current.png',
        '/images/projects/stackhal-home.jpg',
        '/images/projects/obd-telemetry.jpg',
        '/images/projects/obd-diagnostics.jpg',
    ];

    public const MAX_ITEMS = 40;
    public const MAX_LIST_ENTRIES = 30;
    public const MAX_TEXT_LENGTH = 5000;

    /**
     * @return array<string, array{
     *     label: string, item: string, group: string, description: string, title: list<string>,
     *     fields: array<string, array{type: string, label: string, shared?: bool, hint?: string, add?: string, badge?: string}>
     * }>
     */
    public static function collections(): array
    {
        return [
            'experience' => [
                'label' => 'Work experience', 'item' => 'job', 'group' => 'Résumé',
                'description' => 'Jobs on the résumé, in this order. Jobs with “Show on home page” also appear on the home page.',
                'title' => ['role', 'company', 'period'],
                'fields' => [
                    'company' => ['type' => 'text', 'label' => 'Company'],
                    'role' => ['type' => 'text', 'label' => 'Role'],
                    'period' => ['type' => 'text', 'label' => 'Period', 'hint' => 'For example “2022 - Present”'],
                    'location' => ['type' => 'text', 'label' => 'Location'],
                    'highlights' => ['type' => 'list', 'label' => 'Highlights', 'add' => 'Add highlight'],
                    'on_home' => ['type' => 'bool', 'label' => 'Show on home page', 'badge' => 'Home page'],
                ],
            ],
            'skills' => [
                'label' => 'Skills', 'item' => 'skill group', 'group' => 'Résumé',
                'description' => 'Skill groups in the “Technical Expertise” block of the résumé.',
                'title' => ['name'],
                'fields' => [
                    'name' => ['type' => 'text', 'label' => 'Group name'],
                    'items' => ['type' => 'list', 'label' => 'Skills', 'add' => 'Add skill'],
                ],
            ],
            'reviews' => [
                'label' => 'Client reviews', 'item' => 'review', 'group' => 'Résumé',
                'description' => 'Client quotes shown on the résumé and the services page.',
                'title' => ['quote'],
                'fields' => [
                    'quote' => ['type' => 'textarea', 'label' => 'Quote'],
                    'context' => ['type' => 'text', 'label' => 'Project', 'hint' => 'Shown under the quote on the services page'],
                ],
            ],
            'services' => [
                'label' => 'Services', 'item' => 'service', 'group' => 'Services',
                'description' => 'Service cards. Numbers (01, 02…) follow the order.',
                'title' => ['title'],
                'fields' => [
                    'tag' => ['type' => 'text', 'label' => 'Tag'],
                    'title' => ['type' => 'text', 'label' => 'Title'],
                    'problem' => ['type' => 'textarea', 'label' => 'Problem'],
                    'solution' => ['type' => 'textarea', 'label' => 'Solution'],
                    'deliverables' => ['type' => 'textarea', 'label' => 'Deliverables'],
                    'ideal_for' => ['type' => 'textarea', 'label' => 'Ideal for'],
                ],
            ],
            'process' => [
                'label' => 'Process steps', 'item' => 'step', 'group' => 'Services',
                'description' => '“How we work together” steps. Numbers follow the order.',
                'title' => ['title'],
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Title'],
                    'text' => ['type' => 'textarea', 'label' => 'Text'],
                ],
            ],
            'faq' => [
                'label' => 'FAQ', 'item' => 'question', 'group' => 'Services',
                'description' => 'Questions and answers on the services page. Search engines see them too.',
                'title' => ['question'],
                'fields' => [
                    'question' => ['type' => 'text', 'label' => 'Question'],
                    'answer' => ['type' => 'textarea', 'label' => 'Answer'],
                ],
            ],
            'consultation_points' => [
                'label' => 'Consultation points', 'item' => 'point', 'group' => 'Services',
                'description' => 'Bullet points next to the contact form on the services page.',
                'title' => ['text'],
                'fields' => [
                    'text' => ['type' => 'text', 'label' => 'Point'],
                ],
            ],
            'projects' => [
                'label' => 'Projects', 'item' => 'project', 'group' => 'Home',
                'description' => 'Product cards on the home page. They are also linked in the footer.',
                'title' => ['name', 'label'],
                'fields' => [
                    'name' => ['type' => 'text', 'label' => 'Name', 'shared' => true],
                    'url' => ['type' => 'url', 'label' => 'Website'],
                    'image' => ['type' => 'image', 'label' => 'Screenshot'],
                    'image_alt' => ['type' => 'text', 'label' => 'Screenshot description', 'hint' => 'Read aloud by screen readers'],
                    'label' => ['type' => 'text', 'label' => 'Label'],
                    'title' => ['type' => 'text', 'label' => 'Headline'],
                    'text' => ['type' => 'textarea', 'label' => 'Description'],
                ],
            ],
            'facts' => [
                'label' => 'Quick facts', 'item' => 'fact', 'group' => 'Home',
                'description' => 'Short facts under the introduction on the home page.',
                'title' => ['text'],
                'fields' => [
                    'text' => ['type' => 'text', 'label' => 'Fact'],
                ],
            ],
            'ai_activity' => [
                'label' => 'AI notes · activity', 'item' => 'tool', 'group' => 'Home',
                'description' => '“How active you are” in the AI notes block.',
                'title' => ['title'],
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Tool'],
                    'text' => ['type' => 'textarea', 'label' => 'Text'],
                ],
            ],
            'ai_ideas' => [
                'label' => 'AI notes · ideas', 'item' => 'note', 'group' => 'Home',
                'description' => '“Where the ideas come from” in the AI notes block.',
                'title' => ['title'],
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Lead'],
                    'text' => ['type' => 'textarea', 'label' => 'Text'],
                ],
            ],
            'ai_approach' => [
                'label' => 'AI notes · approach', 'item' => 'note', 'group' => 'Home',
                'description' => '“What the approach is” in the AI notes block.',
                'title' => ['title'],
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Lead'],
                    'text' => ['type' => 'textarea', 'label' => 'Text'],
                ],
            ],
        ];
    }

    /**
     * Single texts grouped by page. The first matching prefix wins; keys listed in
     * SHARED_TEXTS are addresses that are the same in both languages.
     *
     * @return array<string, array{label: string, group: string, prefixes: list<string>}>
     */
    public static function textGroups(): array
    {
        return [
            'home-seo' => ['label' => 'Home · Search appearance', 'group' => 'Home', 'prefixes' => ['about.meta.']],
            'ai-notes' => ['label' => 'Home · AI notes headings', 'group' => 'Home', 'prefixes' => ['about.ai_dump.']],
            'story' => ['label' => 'Home · Background', 'group' => 'Home', 'prefixes' => ['about.story.']],
            'products' => ['label' => 'Home · Projects heading', 'group' => 'Home', 'prefixes' => ['about.ecosystem.']],
            'next' => ['label' => 'Home · Next steps', 'group' => 'Home', 'prefixes' => ['about.gateways.', 'about.summit.']],
            'home' => ['label' => 'Home · Introduction', 'group' => 'Home', 'prefixes' => ['about.']],
            'obd' => ['label' => 'OBD Vault', 'group' => 'Home', 'prefixes' => ['showcase.obd.', 'links.obd_']],
            'resume-seo' => ['label' => 'Résumé · Search appearance', 'group' => 'Résumé', 'prefixes' => ['resume_page.meta.']],
            'resume' => ['label' => 'Résumé · Page text', 'group' => 'Résumé', 'prefixes' => ['resume_page.']],
            'services-seo' => ['label' => 'Services · Search appearance', 'group' => 'Services', 'prefixes' => ['services_page.meta.']],
            'services' => ['label' => 'Services · Page text', 'group' => 'Services', 'prefixes' => ['services_page.']],
            'shared' => ['label' => 'Header, footer & buttons', 'group' => 'Everywhere', 'prefixes' => ['base.', 'showcase.']],
            'contact' => ['label' => 'Contact form', 'group' => 'Everywhere', 'prefixes' => ['contact.']],
            'not-found' => ['label' => 'Page not found (404)', 'group' => 'Everywhere', 'prefixes' => ['error404.', '']],
        ];
    }

    public const SHARED_TEXTS = ['links.obd_website', 'links.obd_app_store', 'links.obd_google_play'];

    public static function textGroupFor(string $key): string
    {
        foreach (self::textGroups() as $id => $group) {
            foreach ($group['prefixes'] as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    return $id;
                }
            }
        }

        return 'not-found';
    }
}
