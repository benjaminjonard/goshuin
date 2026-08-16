<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814222702 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Let a location record the deities it enshrines and when it was founded.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql("ALTER TABLE gos_location ADD deities JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE gos_location ALTER deities DROP DEFAULT');
        $this->addSql('ALTER TABLE gos_location ADD foundation VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
