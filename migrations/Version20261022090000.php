<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace per-string site_content with one structured bilingual site_document';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS site_content');
        $this->addSql('CREATE TABLE site_document ('
            . 'id VARCHAR(32) NOT NULL, draft JSON DEFAULT NULL, published JSON DEFAULT NULL, '
            . 'revision INT NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE site_document');
        $this->addSql('CREATE TABLE site_content ('
            . 'locale VARCHAR(2) NOT NULL, draft JSON NOT NULL, published JSON NOT NULL, '
            . 'revision INT NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(locale))');
    }
}
