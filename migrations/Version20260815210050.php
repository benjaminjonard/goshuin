<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260815210050 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Turn the typed cities and prefectures into rows of their own, before the columns holding them go.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql(<<<'SQL'
            INSERT INTO gos_prefecture (id, name, created_at)
            SELECT DISTINCT ON (LOWER(TRIM(prefecture)))
                   gen_random_uuid()::text, TRIM(prefecture), NOW()
            FROM gos_location
            WHERE TRIM(COALESCE(prefecture, '')) <> ''
            ORDER BY LOWER(TRIM(prefecture))
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO gos_city (id, name, prefecture_id, created_at)
            SELECT gen_random_uuid()::text, named.name, prefecture.id, NOW()
            FROM (
                SELECT DISTINCT ON (LOWER(TRIM(locality)))
                       TRIM(locality) AS name, TRIM(prefecture) AS prefecture
                FROM gos_location
                WHERE TRIM(COALESCE(locality, '')) <> ''
                ORDER BY LOWER(TRIM(locality)), TRIM(prefecture) NULLS LAST
            ) AS named
            LEFT JOIN gos_prefecture prefecture ON LOWER(prefecture.name) = LOWER(named.prefecture)
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE gos_location location
            SET city_id = city.id
            FROM gos_city city
            WHERE LOWER(city.name) = LOWER(TRIM(location.locality))
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE gos_location location
            SET prefecture_id = prefecture.id
            FROM gos_prefecture prefecture
            WHERE LOWER(prefecture.name) = LOWER(TRIM(location.prefecture))
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
