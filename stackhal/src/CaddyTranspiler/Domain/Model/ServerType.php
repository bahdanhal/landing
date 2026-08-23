<?php

declare(strict_types=1);

namespace App\CaddyTranspiler\Domain\Model;

enum ServerType: string
{
    case Nginx = 'nginx';
    case Apache = 'apache';

    public static function fromString(string $value): self
    {
        return match (strtolower(trim($value))) {
            'apache', 'htaccess', '.htaccess', 'vhost' => self::Apache,
            default => self::Nginx,
        };
    }
}
