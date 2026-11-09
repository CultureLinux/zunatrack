<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261103090132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow a series to have references from multiple catalog providers.';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }
        $this->addSql('ALTER TABLE catalog_source DROP INDEX UNIQ_ED8E673A5278319C, ADD INDEX IDX_ED8E673A5278319C (series_id)');
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }
        $this->addSql('ALTER TABLE catalog_source DROP INDEX IDX_ED8E673A5278319C, ADD UNIQUE INDEX UNIQ_ED8E673A5278319C (series_id)');
    }
}
