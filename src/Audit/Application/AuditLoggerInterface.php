<?php

declare(strict_types=1);

namespace App\Audit\Application;

interface AuditLoggerInterface
{
    public function newAuditId(): string;

    public function log(string $event, array $payload = []): void;

    public function safeUrl(string $url): string;
}
