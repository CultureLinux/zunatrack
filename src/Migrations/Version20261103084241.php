<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261103084241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the local series catalog and provider snapshot tables.';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('CREATE TABLE series (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, summary CLOB DEFAULT NULL, poster_url VARCHAR(500) DEFAULT NULL, status VARCHAR(40) DEFAULT NULL, genres CLOB NOT NULL, premiered_at DATETIME DEFAULT NULL, ended_at DATETIME DEFAULT NULL, updated_at DATETIME NOT NULL)');
            $this->addSql('CREATE TABLE season (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, number INTEGER NOT NULL, name VARCHAR(255) DEFAULT NULL, series_id INTEGER NOT NULL, CONSTRAINT FK_F0E45BA95278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_season_number ON season (series_id, number)');
            $this->addSql('CREATE TABLE episode (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, number INTEGER NOT NULL, title VARCHAR(255) NOT NULL, summary CLOB DEFAULT NULL, air_date DATETIME DEFAULT NULL, season_id INTEGER NOT NULL, CONSTRAINT FK_DDAA1CDA4EC001D1 FOREIGN KEY (season_id) REFERENCES season (id) ON DELETE CASCADE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_episode_number ON episode (season_id, number)');
            $this->addSql('CREATE TABLE catalog_source (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, provider VARCHAR(40) NOT NULL, external_id VARCHAR(100) NOT NULL, payload CLOB NOT NULL, fetched_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, series_id INTEGER NOT NULL UNIQUE, CONSTRAINT FK_ED8E673A5278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_catalog_source ON catalog_source (provider, external_id)');
            return;
        }
        $this->addSql('CREATE TABLE catalog_source (id INT AUTO_INCREMENT NOT NULL, provider VARCHAR(40) NOT NULL, external_id VARCHAR(100) NOT NULL, payload JSON NOT NULL, fetched_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, series_id INT NOT NULL, UNIQUE INDEX UNIQ_ED8E673A5278319C (series_id), UNIQUE INDEX uniq_catalog_source (provider, external_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE episode (id INT AUTO_INCREMENT NOT NULL, number INT NOT NULL, title VARCHAR(255) NOT NULL, summary LONGTEXT DEFAULT NULL, air_date DATETIME DEFAULT NULL, season_id INT NOT NULL, UNIQUE INDEX uniq_episode_number (season_id, number), INDEX IDX_DDAA1CDA4EC001D1 (season_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE season (id INT AUTO_INCREMENT NOT NULL, number INT NOT NULL, name VARCHAR(255) DEFAULT NULL, series_id INT NOT NULL, UNIQUE INDEX uniq_season_number (series_id, number), INDEX IDX_F0E45BA95278319C (series_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE series (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, summary LONGTEXT DEFAULT NULL, poster_url VARCHAR(500) DEFAULT NULL, status VARCHAR(40) DEFAULT NULL, genres JSON NOT NULL, premiered_at DATETIME DEFAULT NULL, ended_at DATETIME DEFAULT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE catalog_source ADD CONSTRAINT FK_ED8E673A5278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE episode ADD CONSTRAINT FK_DDAA1CDA4EC001D1 FOREIGN KEY (season_id) REFERENCES season (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE season ADD CONSTRAINT FK_F0E45BA95278319C FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('DROP TABLE catalog_source'); $this->addSql('DROP TABLE episode'); $this->addSql('DROP TABLE season'); $this->addSql('DROP TABLE series'); return;
        }
        $this->addSql('ALTER TABLE catalog_source DROP FOREIGN KEY FK_ED8E673A5278319C');
        $this->addSql('ALTER TABLE episode DROP FOREIGN KEY FK_DDAA1CDA4EC001D1');
        $this->addSql('ALTER TABLE season DROP FOREIGN KEY FK_F0E45BA95278319C');
        $this->addSql('DROP TABLE catalog_source');
        $this->addSql('DROP TABLE episode');
        $this->addSql('DROP TABLE season');
        $this->addSql('DROP TABLE series');
    }
}
