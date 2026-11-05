<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104160100 extends AbstractMigration
{
    public function getDescription(): string { return 'Store the TVmaze candidate selected for an import replay.'; }
    public function up(Schema $schema): void { $this->addSql($this->connection->getDatabasePlatform() instanceof SQLitePlatform ? 'ALTER TABLE import_item ADD COLUMN selected_candidate_id VARCHAR(64) DEFAULT NULL' : 'ALTER TABLE import_item ADD selected_candidate_id VARCHAR(64) DEFAULT NULL'); }
    public function down(Schema $schema): void { if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) $this->addSql('ALTER TABLE import_item DROP selected_candidate_id'); }
}
