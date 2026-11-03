<?php
declare(strict_types=1);
namespace App\Migrations;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261103100000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create per-user personal series library entries.'; }
    public function up(Schema $schema): void { if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) { $this->addSql('CREATE TABLE library_entry (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, series_id INTEGER NOT NULL, status VARCHAR(16) NOT NULL, note CLOB DEFAULT NULL, added_at DATETIME NOT NULL, started_at DATETIME DEFAULT NULL, finished_at DATETIME DEFAULT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_LIBRARY_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE, CONSTRAINT FK_LIBRARY_SERIES FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE)'); $this->addSql('CREATE UNIQUE INDEX uniq_library_user_series ON library_entry (user_id, series_id)'); return; } $this->addSql('CREATE TABLE library_entry (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, series_id INT NOT NULL, status VARCHAR(16) NOT NULL, note LONGTEXT DEFAULT NULL, added_at DATETIME NOT NULL, started_at DATETIME DEFAULT NULL, finished_at DATETIME DEFAULT NULL, updated_at DATETIME NOT NULL, INDEX IDX_18844F5FA76ED395 (user_id), INDEX IDX_18844F5F5278319C (series_id), UNIQUE INDEX uniq_library_user_series (user_id, series_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4'); $this->addSql('ALTER TABLE library_entry ADD CONSTRAINT FK_LIBRARY_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE'); $this->addSql('ALTER TABLE library_entry ADD CONSTRAINT FK_LIBRARY_SERIES FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE'); }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE library_entry'); }
}
