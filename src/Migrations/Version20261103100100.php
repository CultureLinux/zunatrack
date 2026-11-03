<?php
declare(strict_types=1);
namespace App\Migrations;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261103100100 extends AbstractMigration
{
    public function getDescription(): string { return 'Align library foreign-key index names with Doctrine mapping.'; }
    public function up(Schema $schema): void { if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) { $this->addSql('ALTER TABLE library_entry RENAME INDEX IDX_LIBRARY_USER TO IDX_18844F5FA76ED395'); $this->addSql('ALTER TABLE library_entry RENAME INDEX IDX_LIBRARY_SERIES TO IDX_18844F5F5278319C'); } }
    public function down(Schema $schema): void { if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) { $this->addSql('ALTER TABLE library_entry RENAME INDEX IDX_18844F5FA76ED395 TO IDX_LIBRARY_USER'); $this->addSql('ALTER TABLE library_entry RENAME INDEX IDX_18844F5F5278319C TO IDX_LIBRARY_SERIES'); } }
}
