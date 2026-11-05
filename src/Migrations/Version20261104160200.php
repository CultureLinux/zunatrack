<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104160200 extends AbstractMigration
{
    public function getDescription(): string { return 'Align import table index names with Doctrine mapping.'; }
    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) return;
        $this->addSql('ALTER TABLE import_batch RENAME INDEX idx_import_batch_user TO IDX_155EFD1AA76ED395');
        $this->addSql('ALTER TABLE import_item RENAME INDEX idx_import_item_batch TO IDX_F237CB9EF39EBE7A');
    }
    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) return;
        $this->addSql('ALTER TABLE import_batch RENAME INDEX IDX_155EFD1AA76ED395 TO idx_import_batch_user');
        $this->addSql('ALTER TABLE import_item RENAME INDEX IDX_F237CB9EF39EBE7A TO idx_import_item_batch');
    }
}
