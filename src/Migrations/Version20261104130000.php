<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add instance settings, browser push subscriptions, and episode delivery trace.';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('CREATE TABLE instance_configuration (id INTEGER NOT NULL, public_registration BOOLEAN NOT NULL, account_limit INTEGER DEFAULT NULL, vapid_subject VARCHAR(255) DEFAULT NULL, vapid_public_key CLOB DEFAULT NULL, encrypted_vapid_private_key CLOB DEFAULT NULL, PRIMARY KEY (id))');
            $this->addSql('CREATE TABLE push_subscription (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, endpoint CLOB NOT NULL, endpoint_hash VARCHAR(64) NOT NULL, p256dh CLOB NOT NULL, auth CLOB NOT NULL, activated_at DATETIME NOT NULL, CONSTRAINT FK_PUSH_SUBSCRIPTION_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_push_subscription_endpoint ON push_subscription (endpoint_hash)');
            $this->addSql('CREATE TABLE push_delivery (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, episode_id INTEGER NOT NULL, subscription_id INTEGER NOT NULL, queued_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, CONSTRAINT FK_PUSH_DELIVERY_EPISODE FOREIGN KEY (episode_id) REFERENCES episode (id) ON DELETE CASCADE, CONSTRAINT FK_PUSH_DELIVERY_SUBSCRIPTION FOREIGN KEY (subscription_id) REFERENCES push_subscription (id) ON DELETE CASCADE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_push_delivery_episode_subscription ON push_delivery (episode_id, subscription_id)');

            return;
        }
        $this->addSql('CREATE TABLE instance_configuration (id INT NOT NULL, public_registration TINYINT(1) NOT NULL, account_limit INT DEFAULT NULL, vapid_subject VARCHAR(255) DEFAULT NULL, vapid_public_key LONGTEXT DEFAULT NULL, encrypted_vapid_private_key LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE push_subscription (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, endpoint LONGTEXT NOT NULL, endpoint_hash VARCHAR(64) NOT NULL, p256dh LONGTEXT NOT NULL, auth LONGTEXT NOT NULL, activated_at DATETIME NOT NULL, INDEX IDX_PUSH_SUBSCRIPTION_USER (user_id), UNIQUE INDEX uniq_push_subscription_endpoint (endpoint_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE push_delivery (id INT AUTO_INCREMENT NOT NULL, episode_id INT NOT NULL, subscription_id INT NOT NULL, queued_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_PUSH_DELIVERY_EPISODE (episode_id), INDEX IDX_PUSH_DELIVERY_SUBSCRIPTION (subscription_id), UNIQUE INDEX uniq_push_delivery_episode_subscription (episode_id, subscription_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE push_subscription ADD CONSTRAINT FK_PUSH_SUBSCRIPTION_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE push_delivery ADD CONSTRAINT FK_PUSH_DELIVERY_EPISODE FOREIGN KEY (episode_id) REFERENCES episode (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE push_delivery ADD CONSTRAINT FK_PUSH_DELIVERY_SUBSCRIPTION FOREIGN KEY (subscription_id) REFERENCES push_subscription (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE push_delivery');
        $this->addSql('DROP TABLE push_subscription');
        $this->addSql('DROP TABLE instance_configuration');
    }
}
