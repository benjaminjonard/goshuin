<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260812182705 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Give the goshuincho its two cover slots.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE gos_goshuincho ADD cover_front VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_goshuincho ADD cover_back VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
