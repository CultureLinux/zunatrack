<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Store TVmaze popularity and ratings for lightweight local catalogue suggestions.'; }
    public function up(Schema $schema): void { if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) { $this->addSql('ALTER TABLE series ADD COLUMN popularity DOUBLE PRECISION DEFAULT NULL'); $this->addSql('ALTER TABLE series ADD COLUMN rating DOUBLE PRECISION DEFAULT NULL'); return; } $this->addSql('ALTER TABLE series ADD popularity DOUBLE PRECISION DEFAULT NULL, ADD rating DOUBLE PRECISION DEFAULT NULL'); }
    public function down(Schema $schema): void { if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) $this->addSql('ALTER TABLE series DROP popularity, DROP rating'); }
}
