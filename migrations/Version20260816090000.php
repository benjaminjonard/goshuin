<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260816090000 extends AbstractMigration
{
    private const array SLUGGED = [
        'gos_deity' => ['name', 'un_deity_slug'],
        'gos_city' => ['name', 'un_city_slug'],
        'gos_prefecture' => ['name', 'un_prefecture_slug'],
        'gos_location' => ['romanized_name', 'un_location_slug'],
    ];

    public function getDescription(): string
    {
        return 'Give every deity, city, prefecture and location a slug to be addressed by.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        foreach (self::SLUGGED as $table => [$column, $index]) {
            $this->addSql(sprintf('ALTER TABLE %s ADD slug VARCHAR(255) DEFAULT NULL', $table));

            $this->addSql(sprintf(<<<'SQL'
                UPDATE %s SET slug = NULLIF(trim(both '-' from regexp_replace(
                    lower(translate(%s,
                        'āáàâäãĀÁÀÂÄÃīíìîïĪÍÌÎÏūúùûüŪÚÙÛÜēéèêëĒÉÈÊËōóòôöõŌÓÒÔÖÕñÑçÇ',
                        'aaaaaaAAAAAAiiiiiIIIIIuuuuuUUUUUeeeeeEEEEEooooooOOOOOOnNcC'
                    )),
                    '[^a-z0-9]+', '-', 'g'
                )), '')
                SQL, $table, $column));

            $this->addSql(sprintf(<<<'SQL'
                UPDATE %1$s AS t SET slug = coalesce(t.slug || '-', '') || right(t.id, 8)
                FROM (
                    SELECT id, row_number() OVER (PARTITION BY slug ORDER BY created_at, id) AS rank
                    FROM %1$s
                ) AS taken
                WHERE taken.id = t.id AND (t.slug IS NULL OR taken.rank > 1)
                SQL, $table));

            $this->addSql(sprintf('ALTER TABLE %s ALTER slug SET NOT NULL', $table));
            $this->addSql(sprintf('CREATE UNIQUE INDEX %s ON %s (slug)', $index, $table));
        }
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        foreach (self::SLUGGED as $table => [, $index]) {
            $this->addSql(sprintf('DROP INDEX %s', $index));
            $this->addSql(sprintf('ALTER TABLE %s DROP slug', $table));
        }
    }
}
