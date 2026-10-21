<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'site_content')]
class SiteContentEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 2)]
    public string $locale;

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    public array $draft = [];

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    public array $published = [];

    #[ORM\Column(type: Types::INTEGER)]
    public int $revision = 0;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    public \DateTimeImmutable $updatedAt;
}
