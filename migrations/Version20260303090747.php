<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260303090747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_log CHANGE entity_name entity_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE category CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE created_by created_by VARCHAR(255) DEFAULT NULL, CHANGE updated_by updated_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE intervention CHANGE end_date end_date DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE created_by created_by VARCHAR(255) DEFAULT NULL, CHANGE updated_by updated_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE media CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE created_by created_by VARCHAR(255) DEFAULT NULL, CHANGE updated_by updated_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE notification CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE created_by created_by VARCHAR(255) DEFAULT NULL, CHANGE updated_by updated_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE signalement CHANGE address address VARCHAR(500) DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE created_by created_by VARCHAR(255) DEFAULT NULL, CHANGE updated_by updated_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE created_by created_by VARCHAR(255) DEFAULT NULL, CHANGE updated_by updated_by VARCHAR(255) DEFAULT NULL, CHANGE refresh_token refresh_token VARCHAR(500) DEFAULT NULL, CHANGE language language VARCHAR(5) DEFAULT \'en\' NOT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_log CHANGE entity_name entity_name VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE category CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE created_by created_by VARCHAR(255) DEFAULT \'NULL\', CHANGE updated_by updated_by VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE intervention CHANGE end_date end_date DATETIME DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE created_by created_by VARCHAR(255) DEFAULT \'NULL\', CHANGE updated_by updated_by VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE media CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE created_by created_by VARCHAR(255) DEFAULT \'NULL\', CHANGE updated_by updated_by VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE notification CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE created_by created_by VARCHAR(255) DEFAULT \'NULL\', CHANGE updated_by updated_by VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE signalement CHANGE address address VARCHAR(500) DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE created_by created_by VARCHAR(255) DEFAULT \'NULL\', CHANGE updated_by updated_by VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE user CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\', CHANGE created_by created_by VARCHAR(255) DEFAULT \'NULL\', CHANGE updated_by updated_by VARCHAR(255) DEFAULT \'NULL\', CHANGE refresh_token refresh_token VARCHAR(500) DEFAULT \'NULL\', CHANGE language language VARCHAR(5) DEFAULT \'\'\'en\'\'\' NOT NULL');
    }
}
