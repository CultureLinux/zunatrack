<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261103151234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE episode_progress RENAME INDEX idx_7ebfbcd3a76ed395 TO IDX_EB87DB34A76ED395');
        $this->addSql('ALTER TABLE episode_progress RENAME INDEX idx_7ebfbcd3362b62a0 TO IDX_EB87DB34362B62A0');
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE episode_progress RENAME INDEX idx_eb87db34362b62a0 TO IDX_7EBFBCD3362B62A0');
        $this->addSql('ALTER TABLE episode_progress RENAME INDEX idx_eb87db34a76ed395 TO IDX_7EBFBCD3A76ED395');
    }
}
