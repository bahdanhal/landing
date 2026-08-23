<?php

declare(strict_types=1);

namespace App\Pkpass\Domain\Model;

enum PassType: string
{
    case BoardingPass = 'boardingPass';
    case EventTicket = 'eventTicket';
    case Coupon = 'coupon';
    case StoreCard = 'storeCard';
    case Generic = 'generic';

    public static function tryFromKey(string $key): ?self
    {
        return self::tryFrom($key);
    }
}
