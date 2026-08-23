<?php

declare(strict_types=1);

namespace App\CidrMatrix\Domain\Model;

final readonly class SubnetCollision
{
    public function __construct(
        public string $cidrA,
        public string $cidrB,
        public string $overlapCidr,
        public string $startIp,
        public string $endIp,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'cidr_a' => $this->cidrA,
            'cidr_b' => $this->cidrB,
            'overlap_cidr' => $this->overlapCidr,
            'start_ip' => $this->startIp,
            'end_ip' => $this->endIp,
        ];
    }
}
