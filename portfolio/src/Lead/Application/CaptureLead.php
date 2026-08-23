<?php

declare(strict_types=1);

namespace App\Lead\Application;

use App\Lead\Domain\Lead;
use App\Lead\Domain\LeadRepository;

final readonly class CaptureLead
{
    public function __construct(
        private LeadRepository $repository,
        private string $secret,
    ) {
    }

    public function execute(string $email, string $phone, string $message, string $ipAddress, string $source): Lead
    {
        $ipHash = substr(hash_hmac('sha256', $ipAddress, $this->secret), 0, 20);
        $lead = Lead::create($email, $phone, $message, $ipHash, $source);
        $this->repository->save($lead);

        return $lead;
    }
}
