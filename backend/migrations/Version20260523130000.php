<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add view_count to invitation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation ADD COLUMN view_count INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation DROP COLUMN view_count');
    }
}
