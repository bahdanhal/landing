<?php

declare(strict_types=1);

namespace App\CidrMatrix\Domain\Model;

final readonly class CidrBlock
{
    public function __construct(
        public string $rawInput,
        public IpVersion $version,
        public string $ipAddress,
        public int $prefixLength,
        public string $startBytes,
        public string $endBytes,
        public string $networkIp,
        public string $broadcastIp,
        public string $normalizedCidr,
        public bool $isCanonical,
        public string $totalHosts,
    ) {
    }

    public static function parse(string $input): ?self
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        $parts = explode('/', $input, 2);
        $ip = trim($parts[0]);
        $version = IpVersion::fromIpString($ip);
        if ($version === null) {
            return null;
        }

        $maxPrefix = $version === IpVersion::V4 ? 32 : 128;
        if (isset($parts[1])) {
            $prefixString = trim($parts[1]);
            if (!ctype_digit($prefixString)) {
                return null;
            }
            $prefixLength = (int) $prefixString;
            if ($prefixLength < 0 || $prefixLength > $maxPrefix) {
                return null;
            }
        } else {
            $prefixLength = $maxPrefix;
        }

        $packedIp = inet_pton($ip);
        if ($packedIp === false) {
            return null;
        }

        $byteLength = $version === IpVersion::V4 ? 4 : 16;
        $maskBytes = '';
        for ($i = 0; $i < $byteLength; $i++) {
            $bits = min(8, max(0, $prefixLength - ($i * 8)));
            $maskByte = (0xFF << (8 - $bits)) & 0xFF;
            $maskBytes .= chr($maskByte);
        }

        $startBytes = $packedIp & $maskBytes;
        $endBytes = $packedIp | (~$maskBytes);

        $networkIp = inet_ntop($startBytes);
        $broadcastIp = inet_ntop($endBytes);
        if ($networkIp === false || $broadcastIp === false) {
            return null;
        }

        $normalizedCidr = sprintf('%s/%d', $networkIp, $prefixLength);
        $isCanonical = ($ip === $networkIp);

        $hostBits = $maxPrefix - $prefixLength;
        if ($hostBits <= 62) {
            $totalHosts = (string) (2 ** $hostBits);
        } else {
            $totalHosts = sprintf('2^%d', $hostBits);
        }

        return new self(
            rawInput: $input,
            version: $version,
            ipAddress: $ip,
            prefixLength: $prefixLength,
            startBytes: $startBytes,
            endBytes: $endBytes,
            networkIp: $networkIp,
            broadcastIp: $broadcastIp,
            normalizedCidr: $normalizedCidr,
            isCanonical: $isCanonical,
            totalHosts: $totalHosts,
        );
    }

    public function contains(self $other): bool
    {
        if ($this->version !== $other->version) {
            return false;
        }

        return $this->startBytes <= $other->startBytes && $this->endBytes >= $other->endBytes;
    }

    public function overlaps(self $other): bool
    {
        if ($this->version !== $other->version) {
            return false;
        }

        return $this->startBytes <= $other->endBytes && $this->endBytes >= $other->startBytes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'raw_input' => $this->rawInput,
            'version' => $this->version->value,
            'ip_address' => $this->ipAddress,
            'prefix_length' => $this->prefixLength,
            'network_ip' => $this->networkIp,
            'broadcast_ip' => $this->broadcastIp,
            'normalized_cidr' => $this->normalizedCidr,
            'is_canonical' => $this->isCanonical,
            'total_hosts' => $this->totalHosts,
        ];
    }
}
