<?php

declare(strict_types=1);

namespace App\Pkpass\Domain\Model;

enum ValidationSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
}
