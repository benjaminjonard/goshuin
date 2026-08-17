<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817134126 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Let a goshuin carry tags, each one a name of its own that any goshuin of the same collector can share.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE gos_tag (id CHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id CHAR(36) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FB0BFBE07E3C61F9 ON gos_tag (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX un_tag_name ON gos_tag (owner_id, name)');
        $this->addSql('CREATE UNIQUE INDEX un_tag_slug ON gos_tag (owner_id, slug)');
        $this->addSql('ALTER TABLE gos_tag ADD CONSTRAINT FK_FB0BFBE07E3C61F9 FOREIGN KEY (owner_id) REFERENCES gos_user (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('CREATE TABLE gos_goshuin_tag (goshuin_id CHAR(36) NOT NULL, tag_id CHAR(36) NOT NULL, PRIMARY KEY (goshuin_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_D65826F944D9B8A7 ON gos_goshuin_tag (goshuin_id)');
        $this->addSql('CREATE INDEX IDX_D65826F9BAD26311 ON gos_goshuin_tag (tag_id)');
        $this->addSql('ALTER TABLE gos_goshuin_tag ADD CONSTRAINT FK_D65826F944D9B8A7 FOREIGN KEY (goshuin_id) REFERENCES gos_goshuin (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gos_goshuin_tag ADD CONSTRAINT FK_D65826F9BAD26311 FOREIGN KEY (tag_id) REFERENCES gos_tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
