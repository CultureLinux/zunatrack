<?php
declare(strict_types=1);
namespace App\Migrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261103111000 extends AbstractMigration
{
    public function getDescription(): string { return 'Store season premiere dates from the catalog provider.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE season ADD premiered_at DATETIME DEFAULT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE season DROP premiered_at'); }
}
