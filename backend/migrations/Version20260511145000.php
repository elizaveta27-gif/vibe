<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511145000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert json columns to jsonb';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation ALTER COLUMN blocks TYPE jsonb USING blocks::jsonb');
        $this->addSql('ALTER TABLE rsvp_response ALTER COLUMN responses TYPE jsonb USING responses::jsonb');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation ALTER COLUMN blocks TYPE json USING blocks::json');
        $this->addSql('ALTER TABLE rsvp_response ALTER COLUMN responses TYPE json USING responses::json');
    }
}
