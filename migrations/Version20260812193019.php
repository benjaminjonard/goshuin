<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260812193019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the goshuin, whose image is required by the schema and whose position is unique within its goshuincho.';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql(<<<'SQL'
            CREATE TABLE gos_goshuin (
              id CHAR(36) NOT NULL,
              goshuincho_id CHAR(36) NOT NULL,
              owner_id CHAR(36) NOT NULL,
              location_id CHAR(36) NOT NULL,
              received_on DATE NOT NULL,
              position INT NOT NULL,
              image VARCHAR(255) NOT NULL,
              image_mini VARCHAR(255) DEFAULT NULL,
              image_card VARCHAR(255) DEFAULT NULL,
              image_full VARCHAR(255) DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_CCF0CFCDF0BCA936 ON gos_goshuin (goshuincho_id)');
        $this->addSql('CREATE INDEX IDX_CCF0CFCD7E3C61F9 ON gos_goshuin (owner_id)');
        $this->addSql('CREATE INDEX IDX_CCF0CFCD64D218E ON gos_goshuin (location_id)');
        $this->addSql('CREATE UNIQUE INDEX un_goshuin_position ON gos_goshuin (goshuincho_id, position)');
        $this->addSql('ALTER TABLE gos_goshuin ADD CONSTRAINT FK_CCF0CFCDF0BCA936 FOREIGN KEY (goshuincho_id) REFERENCES gos_goshuincho (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE gos_goshuin ADD CONSTRAINT FK_CCF0CFCD7E3C61F9 FOREIGN KEY (owner_id) REFERENCES gos_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE gos_goshuin ADD CONSTRAINT FK_CCF0CFCD64D218E FOREIGN KEY (location_id) REFERENCES gos_location (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(true, 'Always move forward.');
    }
}
