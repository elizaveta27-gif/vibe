<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create analytics_events table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE analytics_events (
                id        BIGSERIAL    PRIMARY KEY,
                event     VARCHAR(100) NOT NULL,
                meta      JSONB        NOT NULL DEFAULT '{}',
                created_at TIMESTAMP   NOT NULL DEFAULT NOW()
            )
        SQL);

        $this->addSql('CREATE INDEX idx_analytics_event ON analytics_events (event)');
        $this->addSql('CREATE INDEX idx_analytics_created ON analytics_events (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS analytics_events');
    }
}
