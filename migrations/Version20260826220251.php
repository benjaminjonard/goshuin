<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826220251 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Give every named entity three names — romanized, kanji and kana — none of them required on its own.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        foreach (['city', 'prefecture', 'deity'] as $table) {
            $this->addSql(sprintf('DROP INDEX un_%s_name', $table));
            $this->addSql(sprintf('ALTER TABLE gos_%s RENAME COLUMN name TO romanized_name', $table));
            $this->addSql(sprintf('ALTER TABLE gos_%s ALTER romanized_name DROP NOT NULL', $table));
            $this->addSql(sprintf('ALTER TABLE gos_%s ADD kanji_name VARCHAR(255) DEFAULT NULL', $table));
            $this->addSql(sprintf('ALTER TABLE gos_%s ADD kana_name VARCHAR(255) DEFAULT NULL', $table));
        }

        $this->addSql('ALTER TABLE gos_location ALTER romanized_name DROP NOT NULL');
        $this->addSql('ALTER TABLE gos_location RENAME COLUMN japanese_name TO kanji_name');
        $this->addSql('ALTER TABLE gos_location ADD kana_name VARCHAR(255) DEFAULT NULL');

        $this->addSql('UPDATE gos_deity SET kanji_name = romanized_name, romanized_name = NULL WHERE romanized_name ~ \'^[\u3000-\u30ff\u3400-\u4dbf\u4e00-\u9fff\uf900-\ufaff\uff66-\uff9f]\'');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
