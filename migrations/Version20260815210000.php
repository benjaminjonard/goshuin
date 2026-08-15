<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260815210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make a city and a prefecture things of their own, each carrying photographs and notes.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE gos_prefecture (id CHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, notes TEXT DEFAULT NULL, photograph VARCHAR(255) DEFAULT NULL, photograph_mini VARCHAR(255) DEFAULT NULL, photograph_card VARCHAR(255) DEFAULT NULL, photograph_full VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX un_prefecture_name ON gos_prefecture (name)');

        $this->addSql('CREATE TABLE gos_city (id CHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, notes TEXT DEFAULT NULL, photograph VARCHAR(255) DEFAULT NULL, photograph_mini VARCHAR(255) DEFAULT NULL, photograph_card VARCHAR(255) DEFAULT NULL, photograph_full VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, prefecture_id CHAR(36) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F918B09A9D39C865 ON gos_city (prefecture_id)');
        $this->addSql('CREATE UNIQUE INDEX un_city_name ON gos_city (name)');
        $this->addSql('ALTER TABLE gos_city ADD CONSTRAINT FK_F918B09A9D39C865 FOREIGN KEY (prefecture_id) REFERENCES gos_prefecture (id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql('CREATE TABLE gos_prefecture_photo (id CHAR(36) NOT NULL, position INT NOT NULL, label VARCHAR(255) DEFAULT NULL, image VARCHAR(255) NOT NULL, image_mini VARCHAR(255) DEFAULT NULL, image_card VARCHAR(255) DEFAULT NULL, image_full VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, prefecture_id CHAR(36) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B5CE0FB59D39C865 ON gos_prefecture_photo (prefecture_id)');
        $this->addSql('CREATE UNIQUE INDEX un_prefecture_photo_position ON gos_prefecture_photo (prefecture_id, position)');
        $this->addSql('ALTER TABLE gos_prefecture_photo ADD CONSTRAINT FK_B5CE0FB59D39C865 FOREIGN KEY (prefecture_id) REFERENCES gos_prefecture (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('CREATE TABLE gos_city_photo (id CHAR(36) NOT NULL, position INT NOT NULL, label VARCHAR(255) DEFAULT NULL, image VARCHAR(255) NOT NULL, image_mini VARCHAR(255) DEFAULT NULL, image_card VARCHAR(255) DEFAULT NULL, image_full VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, city_id CHAR(36) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_1D0D629A8BAC62AF ON gos_city_photo (city_id)');
        $this->addSql('CREATE UNIQUE INDEX un_city_photo_position ON gos_city_photo (city_id, position)');
        $this->addSql('ALTER TABLE gos_city_photo ADD CONSTRAINT FK_1D0D629A8BAC62AF FOREIGN KEY (city_id) REFERENCES gos_city (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('ALTER TABLE gos_location ADD city_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_location ADD prefecture_id CHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_6F1AC7888BAC62AF ON gos_location (city_id)');
        $this->addSql('CREATE INDEX IDX_6F1AC7889D39C865 ON gos_location (prefecture_id)');
        $this->addSql('ALTER TABLE gos_location ADD CONSTRAINT FK_6F1AC7888BAC62AF FOREIGN KEY (city_id) REFERENCES gos_city (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE gos_location ADD CONSTRAINT FK_6F1AC7889D39C865 FOREIGN KEY (prefecture_id) REFERENCES gos_prefecture (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE gos_location DROP CONSTRAINT FK_6F1AC7888BAC62AF');
        $this->addSql('ALTER TABLE gos_location DROP CONSTRAINT FK_6F1AC7889D39C865');
        $this->addSql('ALTER TABLE gos_location DROP city_id');
        $this->addSql('ALTER TABLE gos_location DROP prefecture_id');
        $this->addSql('DROP TABLE gos_city_photo');
        $this->addSql('DROP TABLE gos_prefecture_photo');
        $this->addSql('DROP TABLE gos_city');
        $this->addSql('DROP TABLE gos_prefecture');
    }
}
