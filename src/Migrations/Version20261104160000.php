<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist owner-scoped CSV import batches and unresolved items.';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('CREATE TABLE import_batch (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, format VARCHAR(32) NOT NULL, state VARCHAR(16) NOT NULL, total INTEGER NOT NULL, processed INTEGER NOT NULL, imported INTEGER NOT NULL, merged INTEGER NOT NULL, unresolved INTEGER NOT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_IMPORT_BATCH_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE INDEX IDX_IMPORT_BATCH_USER ON import_batch (user_id)');
            $this->addSql('CREATE TABLE import_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, batch_id INTEGER NOT NULL, external_key VARCHAR(80) NOT NULL, title VARCHAR(255) NOT NULL, payload CLOB NOT NULL, state VARCHAR(16) NOT NULL, reason CLOB DEFAULT NULL, candidates CLOB NOT NULL, cover_url VARCHAR(500) DEFAULT NULL, CONSTRAINT FK_IMPORT_ITEM_BATCH FOREIGN KEY (batch_id) REFERENCES import_batch (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_import_item_batch_external ON import_item (batch_id, external_key)');
            $this->addSql('CREATE INDEX IDX_IMPORT_ITEM_BATCH ON import_item (batch_id)');

            return;
        }
        $this->addSql('CREATE TABLE import_batch (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, format VARCHAR(32) NOT NULL, state VARCHAR(16) NOT NULL, total INT NOT NULL, processed INT NOT NULL, imported INT NOT NULL, merged INT NOT NULL, unresolved INT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_IMPORT_BATCH_USER (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE import_batch ADD CONSTRAINT FK_IMPORT_BATCH_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('CREATE TABLE import_item (id INT AUTO_INCREMENT NOT NULL, batch_id INT NOT NULL, external_key VARCHAR(80) NOT NULL, title VARCHAR(255) NOT NULL, payload JSON NOT NULL, state VARCHAR(16) NOT NULL, reason LONGTEXT DEFAULT NULL, candidates JSON NOT NULL, cover_url VARCHAR(500) DEFAULT NULL, UNIQUE INDEX uniq_import_item_batch_external (batch_id, external_key), INDEX IDX_IMPORT_ITEM_BATCH (batch_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE import_item ADD CONSTRAINT FK_IMPORT_ITEM_BATCH FOREIGN KEY (batch_id) REFERENCES import_batch (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE import_item');
        $this->addSql('DROP TABLE import_batch');
    }
}
