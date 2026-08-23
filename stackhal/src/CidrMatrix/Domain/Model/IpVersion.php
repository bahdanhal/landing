<?php

declare(strict_types=1);

namespace App\CidrMatrix\Domain\Model;

enum IpVersion: string
{
    case V4 = 'v4';
    case V6 = 'v6';

    public static function fromIpString(string $ip): ?self
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return self::V4;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return self::V6;
        }

        return null;
    }
}
