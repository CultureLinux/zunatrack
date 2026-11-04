<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104110000 extends AbstractMigration
{
    public function getDescription(): string { return 'Store the locally cached TVmaze cast for each series.'; }
    public function up(Schema $schema): void { if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) { $this->addSql("ALTER TABLE series ADD COLUMN cast CLOB NOT NULL DEFAULT '[]'"); return; } $this->addSql('ALTER TABLE series ADD cast JSON DEFAULT NULL'); $this->addSql('UPDATE series SET cast = JSON_ARRAY() WHERE cast IS NULL'); $this->addSql('ALTER TABLE series MODIFY cast JSON NOT NULL'); }
    public function down(Schema $schema): void { if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) $this->addSql('ALTER TABLE series DROP cast'); }
}
