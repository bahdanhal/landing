<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite indexes for analytics summary queries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE INDEX IF NOT EXISTS idx_page_views_occurred_at_source '
            . 'ON page_views (occurred_at, source)'
        );
        $this->addSql(
            'CREATE INDEX IF NOT EXISTS idx_page_views_occurred_at_referrer '
            . 'ON page_views (occurred_at, referrer_host)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_page_views_occurred_at_referrer');
        $this->addSql('DROP INDEX IF EXISTS idx_page_views_occurred_at_source');
    }
}
