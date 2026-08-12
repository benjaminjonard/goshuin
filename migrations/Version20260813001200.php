<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813001200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the photographs a goshuin carries, ordered within their type, each owned in its own right.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql(<<<'SQL'
            CREATE TABLE gos_photo (
              id CHAR(36) NOT NULL,
              goshuin_id CHAR(36) NOT NULL,
              owner_id CHAR(36) NOT NULL,
              type VARCHAR(8) NOT NULL,
              position INT NOT NULL,
              label VARCHAR(255) DEFAULT NULL,
              image VARCHAR(255) NOT NULL,
              image_mini VARCHAR(255) DEFAULT NULL,
              image_card VARCHAR(255) DEFAULT NULL,
              image_full VARCHAR(255) DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_250D494544D9B8A7 ON gos_photo (goshuin_id)');
        $this->addSql('CREATE INDEX IDX_250D49457E3C61F9 ON gos_photo (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX un_photo_position ON gos_photo (goshuin_id, type, position)');
        $this->addSql('ALTER TABLE gos_photo ADD CONSTRAINT FK_250D494544D9B8A7 FOREIGN KEY (goshuin_id) REFERENCES gos_goshuin (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE gos_photo ADD CONSTRAINT FK_250D49457E3C61F9 FOREIGN KEY (owner_id) REFERENCES gos_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
