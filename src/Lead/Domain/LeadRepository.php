<?php

declare(strict_types=1);

namespace App\Lead\Domain;

interface LeadRepository
{
    public function save(Lead $lead): void;
}
