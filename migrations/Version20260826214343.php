<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826214343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the slug from every entity that carried one: routes now take the identifier the entity already holds.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP INDEX un_city_slug');
        $this->addSql('ALTER TABLE gos_city DROP slug');
        $this->addSql('DROP INDEX un_deity_slug');
        $this->addSql('ALTER TABLE gos_deity DROP slug');
        $this->addSql('DROP INDEX gos_goshuincho_owner_slug');
        $this->addSql('ALTER TABLE gos_goshuincho DROP slug');
        $this->addSql('DROP INDEX un_location_slug');
        $this->addSql('ALTER TABLE gos_location DROP slug');
        $this->addSql('DROP INDEX un_prefecture_slug');
        $this->addSql('ALTER TABLE gos_prefecture DROP slug');
        $this->addSql('DROP INDEX un_tag_slug');
        $this->addSql('ALTER TABLE gos_tag DROP slug');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
