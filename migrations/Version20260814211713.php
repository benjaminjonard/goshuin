<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814211713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Give a location a gallery of photographs, shared as the location is.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE gos_location_photo (id CHAR(36) NOT NULL, location_id CHAR(36) NOT NULL, position INT NOT NULL, label VARCHAR(255) DEFAULT NULL, image VARCHAR(255) NOT NULL, image_mini VARCHAR(255) DEFAULT NULL, image_card VARCHAR(255) DEFAULT NULL, image_full VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4B76F11C64D218E ON gos_location_photo (location_id)');
        $this->addSql('CREATE UNIQUE INDEX un_location_photo_position ON gos_location_photo (location_id, position)');
        $this->addSql('ALTER TABLE gos_location_photo ADD CONSTRAINT FK_4B76F11C64D218E FOREIGN KEY (location_id) REFERENCES gos_location (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
