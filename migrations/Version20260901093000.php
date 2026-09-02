<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Carry the JIS administrative code of a location, five characters for the municipality and its first two for the prefecture.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE gos_location ADD municipality_code CHAR(5) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_location_municipality_code ON gos_location (municipality_code)');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
