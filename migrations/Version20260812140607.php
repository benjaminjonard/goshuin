<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260812140607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attach the goshuincho to the location it was bought at.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE gos_goshuincho ADD bought_at_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_goshuincho ADD CONSTRAINT FK_AD510D4F6B0C74B7 FOREIGN KEY (bought_at_id) REFERENCES gos_location (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_AD510D4F6B0C74B7 ON gos_goshuincho (bought_at_id)');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
