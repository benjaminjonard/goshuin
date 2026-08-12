<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260812193018 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record each cover derivative in its own column instead of deriving its name.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE gos_goshuincho ADD cover_front_mini VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_goshuincho ADD cover_front_card VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_goshuincho ADD cover_front_full VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_goshuincho ADD cover_back_mini VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_goshuincho ADD cover_back_card VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE gos_goshuincho ADD cover_back_full VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
