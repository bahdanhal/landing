<?php

declare(strict_types=1);

namespace App\Content\Infrastructure;

final class ContentConflictException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The content was saved from another tab. Reload the editor to get the latest version.');
    }
}
