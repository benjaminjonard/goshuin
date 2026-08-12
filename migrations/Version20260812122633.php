<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260812122633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the goshuincho table, with its slug unique per owner and its own hue.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE gos_goshuincho (id CHAR(36) NOT NULL, owner_id CHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, hue INT NOT NULL, purchased_at DATE DEFAULT NULL, price INT DEFAULT NULL, currency VARCHAR(3) DEFAULT \'JPY\' NOT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AD510D4F7E3C61F9 ON gos_goshuincho (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX gos_goshuincho_owner_slug ON gos_goshuincho (owner_id, slug)');
        $this->addSql('ALTER TABLE gos_goshuincho ADD CONSTRAINT FK_AD510D4F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES gos_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
