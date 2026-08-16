<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260816212855 extends AbstractMigration
{
    private const array OWNED = [
        'gos_city' => ['FK_F918B09A7E3C61F9', 'IDX_F918B09A7E3C61F9'],
        'gos_city_photo' => ['FK_1D0D629A7E3C61F9', 'IDX_1D0D629A7E3C61F9'],
        'gos_deity' => ['FK_B25E69397E3C61F9', 'IDX_B25E69397E3C61F9'],
        'gos_location' => ['FK_6F1AC7887E3C61F9', 'IDX_6F1AC7887E3C61F9'],
        'gos_location_photo' => ['FK_4B76F11C7E3C61F9', 'IDX_4B76F11C7E3C61F9'],
        'gos_prefecture' => ['FK_E1D610CF7E3C61F9', 'IDX_E1D610CF7E3C61F9'],
        'gos_prefecture_photo' => ['FK_B5CE0FB57E3C61F9', 'IDX_B5CE0FB57E3C61F9'],
    ];

    private const array SCOPED = [
        'un_city_name' => ['gos_city', 'name'],
        'un_city_slug' => ['gos_city', 'slug'],
        'un_deity_name' => ['gos_deity', 'name'],
        'un_deity_slug' => ['gos_deity', 'slug'],
        'un_location_slug' => ['gos_location', 'slug'],
        'un_prefecture_name' => ['gos_prefecture', 'name'],
        'un_prefecture_slug' => ['gos_prefecture', 'slug'],
    ];

    public function getDescription(): string
    {
        return 'Give every location, city, prefecture, deity and photograph a collector of its own, the referential standing per collector from now on.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        foreach (self::OWNED as $table => [$constraint, $index]) {
            $this->addSql(sprintf('ALTER TABLE %s ADD owner_id CHAR(36) DEFAULT NULL', $table));
            $this->addSql(sprintf('UPDATE %s SET owner_id = (SELECT id FROM gos_user ORDER BY created_at, id LIMIT 1)', $table));
            $this->addSql(sprintf('ALTER TABLE %s ALTER owner_id SET NOT NULL', $table));
            $this->addSql(sprintf('ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (owner_id) REFERENCES gos_user (id) ON DELETE CASCADE NOT DEFERRABLE', $table, $constraint));
            $this->addSql(sprintf('CREATE INDEX %s ON %s (owner_id)', $index, $table));
        }

        foreach (self::SCOPED as $index => [$table, $column]) {
            $this->addSql(sprintf('DROP INDEX %s', $index));
            $this->addSql(sprintf('CREATE UNIQUE INDEX %s ON %s (owner_id, %s)', $index, $table, $column));
        }
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
