<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261103092052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persisted display preferences to user accounts.';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql("ALTER TABLE user ADD locale VARCHAR(8) NOT NULL DEFAULT 'fr'");
            $this->addSql("ALTER TABLE user ADD theme VARCHAR(12) NOT NULL DEFAULT 'system'");
            $this->addSql("ALTER TABLE user ADD density VARCHAR(12) NOT NULL DEFAULT 'normal'");
            $this->addSql("ALTER TABLE user ADD font_size VARCHAR(12) NOT NULL DEFAULT 'normal'");
            $this->addSql("ALTER TABLE user ADD accent VARCHAR(12) NOT NULL DEFAULT 'blue'");

            return;
        }
        $this->addSql('ALTER TABLE user ADD locale VARCHAR(8) NOT NULL, ADD theme VARCHAR(12) NOT NULL, ADD density VARCHAR(12) NOT NULL, ADD font_size VARCHAR(12) NOT NULL, ADD accent VARCHAR(12) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` DROP locale, DROP theme, DROP density, DROP font_size, DROP accent');
    }
}
