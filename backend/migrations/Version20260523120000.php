<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add password_reset_token table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE password_reset_token (
                id         SERIAL PRIMARY KEY,
                user_id    INT NOT NULL,
                token      VARCHAR(64) NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                used_at    TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                CONSTRAINT uq_password_reset_token UNIQUE (token),
                CONSTRAINT fk_prt_user FOREIGN KEY (user_id)
                    REFERENCES "user"(id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_prt_user ON password_reset_token (user_id)');
        $this->addSql("COMMENT ON COLUMN password_reset_token.expires_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN password_reset_token.used_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE password_reset_token');
    }
}
