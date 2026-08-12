<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260812224500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A goshuin accepts a type, a price paid and notes, each independently optional.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql(<<<'SQL'
            ALTER TABLE gos_goshuin
              ADD type VARCHAR(8) DEFAULT NULL,
              ADD price INT DEFAULT NULL,
              ADD currency VARCHAR(3) DEFAULT 'JPY' NOT NULL,
              ADD notes TEXT DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
