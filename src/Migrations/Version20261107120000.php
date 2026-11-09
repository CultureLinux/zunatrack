<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the latest Web Push delivery result for each device.';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('ALTER TABLE push_subscription ADD COLUMN last_result VARCHAR(20) DEFAULT NULL');
            $this->addSql('ALTER TABLE push_subscription ADD COLUMN last_result_at DATETIME DEFAULT NULL');

            return;
        } $this->addSql('ALTER TABLE push_subscription ADD last_result VARCHAR(20) DEFAULT NULL, ADD last_result_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('ALTER TABLE push_subscription DROP last_result, DROP last_result_at');
        }
    }
}
