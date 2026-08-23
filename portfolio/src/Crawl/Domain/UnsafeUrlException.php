<?php

declare(strict_types=1);

namespace App\Crawl\Domain;

use App\Shared\Domain\UnsafeUrlException as SharedUnsafeUrlException;

final class UnsafeUrlException extends SharedUnsafeUrlException
{
}
