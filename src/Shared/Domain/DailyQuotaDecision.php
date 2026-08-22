<?php

namespace App\Shared\Domain;

final readonly class DailyQuotaDecision
{
    public function __construct(public bool $accepted, public int $retryAfterSeconds)
    {
    }
}
