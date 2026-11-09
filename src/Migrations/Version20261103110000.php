<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store cached season and episode artwork plus episode runtime.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE season ADD cover_url VARCHAR(500) DEFAULT NULL');
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('ALTER TABLE episode ADD cover_url VARCHAR(500) DEFAULT NULL');
            $this->addSql('ALTER TABLE episode ADD runtime INTEGER DEFAULT NULL');

            return;
        } $this->addSql('ALTER TABLE episode ADD cover_url VARCHAR(500) DEFAULT NULL, ADD runtime INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        } $this->addSql('ALTER TABLE season DROP cover_url');
        $this->addSql('ALTER TABLE episode DROP cover_url, DROP runtime');
    }
}
