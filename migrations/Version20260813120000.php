<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Give a location a photograph of the place.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE gos_location ADD photograph VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_location ADD photograph_mini VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_location ADD photograph_card VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_location ADD photograph_full VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE gos_location DROP photograph');
        $this->addSql('ALTER TABLE gos_location DROP photograph_mini');
        $this->addSql('ALTER TABLE gos_location DROP photograph_card');
        $this->addSql('ALTER TABLE gos_location DROP photograph_full');
    }
}
