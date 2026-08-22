<?php

declare(strict_types=1);

namespace App\Service;

use App\Lead\Application\CaptureLead;
use App\Lead\Infrastructure\JsonlLeadRepository;

final class ContactLeadStore
{
    private CaptureLead $captureLead;

    public function __construct(
        string $directory,
        string $secret,
        ?CaptureLead $captureLead = null
    ) {
        $this->captureLead = $captureLead ?? new CaptureLead(
            new JsonlLeadRepository($directory),
            $secret
        );
    }

    public function store(string $email, string $ipAddress, string $source): void
    {
        try {
            $this->captureLead->execute($email, $ipAddress, $source);
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException('Invalid contact details provided.', 0, $e);
        } catch (\Exception $e) {
            throw new \RuntimeException('Unable to save your contact request right now.', 0, $e);
        }
    }
}
