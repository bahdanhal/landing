<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The website content edited in the admin: one bilingual document with a draft and a published version.
 * A null version means "use the defaults from content/site.yaml".
 */
#[ORM\Entity]
#[ORM\Table(name: 'site_document')]
class SiteDocumentEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 32)]
    public string $id;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $draft = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $published = null;

    #[ORM\Column(type: Types::INTEGER)]
    public int $revision = 0;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    public \DateTimeImmutable $updatedAt;
}
