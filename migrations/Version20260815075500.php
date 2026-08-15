<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260815075500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Let a deity carry a description and a photograph.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE gos_deity ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_deity ADD photograph VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_deity ADD photograph_mini VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_deity ADD photograph_card VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_deity ADD photograph_full VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE gos_deity DROP description');
        $this->addSql('ALTER TABLE gos_deity DROP photograph');
        $this->addSql('ALTER TABLE gos_deity DROP photograph_mini');
        $this->addSql('ALTER TABLE gos_deity DROP photograph_card');
        $this->addSql('ALTER TABLE gos_deity DROP photograph_full');
    }
}
