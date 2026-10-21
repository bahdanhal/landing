<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store English and Polish website content drafts and published text independently of deployments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE site_content ('
            . 'locale VARCHAR(2) NOT NULL, draft JSON NOT NULL, published JSON NOT NULL, '
            . 'revision INT NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(locale))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE site_content');
    }
}
