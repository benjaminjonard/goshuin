<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814231301 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make a deity a thing of its own, shared between the locations that enshrine it.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE gos_deity (id CHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX un_deity_name ON gos_deity (name)');
        $this->addSql('CREATE TABLE gos_location_deity (location_id CHAR(36) NOT NULL, deity_id CHAR(36) NOT NULL, PRIMARY KEY (location_id, deity_id))');
        $this->addSql('CREATE INDEX IDX_DC25D16064D218E ON gos_location_deity (location_id)');
        $this->addSql('CREATE INDEX IDX_DC25D1604493F9C3 ON gos_location_deity (deity_id)');
        $this->addSql('ALTER TABLE gos_location_deity ADD CONSTRAINT FK_DC25D16064D218E FOREIGN KEY (location_id) REFERENCES gos_location (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gos_location_deity ADD CONSTRAINT FK_DC25D1604493F9C3 FOREIGN KEY (deity_id) REFERENCES gos_deity (id) ON DELETE CASCADE');

        // The names already typed become the first deities rather than being dropped with the column.
        $this->addSql(<<<'SQL'
            INSERT INTO gos_deity (id, name, created_at)
            SELECT gen_random_uuid()::text, named.name, NOW()
            FROM (SELECT DISTINCT TRIM(value) AS name FROM gos_location, jsonb_array_elements_text(deities::jsonb) AS value) AS named
            WHERE named.name <> ''
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO gos_location_deity (location_id, deity_id)
            SELECT DISTINCT location.id, deity.id
            FROM gos_location location, jsonb_array_elements_text(location.deities::jsonb) AS value
            JOIN gos_deity deity ON deity.name = TRIM(value)
            SQL);

        $this->addSql('ALTER TABLE gos_location DROP deities');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql("ALTER TABLE gos_location ADD deities JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE gos_location ALTER deities DROP DEFAULT');
        $this->addSql('DROP TABLE gos_location_deity');
        $this->addSql('DROP TABLE gos_deity');
    }
}
