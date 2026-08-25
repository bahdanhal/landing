<?php

declare(strict_types=1);

namespace App\Lead\Infrastructure;

use App\Lead\Domain\Lead;
use App\Lead\Domain\LeadRepository;
use Bahdan\LeadCaptureBundle\Domain\Lead as BaseLead;
use Bahdan\LeadCaptureBundle\Infrastructure\JsonlLeadRepository as BaseJsonlLeadRepository;

final readonly class JsonlLeadRepository implements LeadRepository
{
    private BaseJsonlLeadRepository $inner;

    public function __construct(string $directory)
    {
        $this->inner = new BaseJsonlLeadRepository($directory);
    }

    public function save(BaseLead $lead): void
    {
        $this->inner->save($lead);
    }

    /** @return list<Lead> */
    public function all(?int $limit = null): array
    {
        $leads = $this->inner->all($limit);

        return array_map(
            static fn ($lead): Lead => new Lead(
                $lead->email,
                $lead->phone,
                $lead->message,
                $lead->ipHash,
                $lead->source,
                $lead->createdAt
            ),
            $leads
        );
    }
}
