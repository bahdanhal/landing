<?php

declare(strict_types=1);

namespace App\Lead\Application;

use App\Lead\Domain\LeadRepository;
use Bahdan\LeadCaptureBundle\Domain\Lead;

final readonly class SearchLeads
{
    public function __construct(private LeadRepository $leads)
    {
    }

    /** @return list<Lead> */
    public function execute(string $query = ''): array
    {
        $search = strtolower(trim($query));
        $allLeads = $this->leads->all();

        return array_values(array_filter($allLeads, static function (Lead $lead) use ($search): bool {
            if ($search === '') {
                return true;
            }

            return str_contains(strtolower($lead->email), $search)
                || str_contains(strtolower($lead->phone), $search)
                || str_contains(strtolower($lead->message), $search)
                || str_contains(strtolower($lead->source), $search);
        }));
    }

    /** @return list<Lead> */
    public function all(): array
    {
        return $this->leads->all();
    }
}
