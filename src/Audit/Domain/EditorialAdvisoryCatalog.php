<?php

declare(strict_types=1);

namespace App\Audit\Domain;

final readonly class EditorialAdvisoryCatalog
{
    /** @return list<array{code:string,title:string,description:string}> */
    public function all(): array
    {
        return [
            [
                'code' => 'site-purpose',
                'title' => 'State one clear purpose for the site',
                'description' => 'Explain who the site serves and why its different sections belong together.',
            ],
            [
                'code' => 'people-first-value',
                'title' => 'Give visitors a complete answer or working outcome',
                'description' => 'Prefer original tools, evidence, experience and analysis over pages created only to '
                    . 'capture query variations.',
            ],
            [
                'code' => 'authorship-and-expertise',
                'title' => 'Make authorship and relevant experience easy to verify',
                'description' => 'Show who created or reviewed important content and link to a useful author or '
                    . 'about page.',
            ],
            [
                'code' => 'sources-and-methodology',
                'title' => 'Publish sources, assumptions and methodology',
                'description' => 'Explain how calculations, comparisons and important claims were produced and when '
                    . 'their inputs were checked.',
            ],
            [
                'code' => 'maintenance-and-dates',
                'title' => 'Use meaningful review dates',
                'description' => 'Display a review date only after the content or underlying data was substantively '
                    . 'checked.',
            ],
            [
                'code' => 'human-review',
                'title' => 'Review the result as a person, not only as a crawler',
                'description' => 'Technical signals cannot establish accuracy, originality, helpfulness or trust. '
                    . 'Finish with a manual review.',
            ],
        ];
    }
}
