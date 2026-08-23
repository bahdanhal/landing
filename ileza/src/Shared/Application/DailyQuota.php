<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Domain\DailyQuotaDecision;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class DailyQuota
{
    public function __construct(private RateLimiterFactory $limiter)
    {
    }

    public function consume(string $clientId, ?\DateTimeImmutable $now = null): DailyQuotaDecision
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $accepted = $this->limiter->create($clientId . '|' . $now->format('Y-m-d'))->consume()->isAccepted();
        $tomorrow = $now->modify('tomorrow')->setTime(0, 0);

        return new DailyQuotaDecision($accepted, max(1, $tomorrow->getTimestamp() - $now->getTimestamp()));
    }
}
