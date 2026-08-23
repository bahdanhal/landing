<?php

declare(strict_types=1);

namespace App\Shared\Domain;

final readonly class DailyQuotaDecision
{
    public function __construct(public bool $accepted, public int $retryAfterSeconds)
    {
    }
}
