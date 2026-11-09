<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the precise provider air timestamp when available.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE episode ADD aired_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE episode DROP aired_at');
    }
}
