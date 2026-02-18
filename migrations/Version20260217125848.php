<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217125848 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_log (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(255) NOT NULL, entity_name VARCHAR(255) DEFAULT NULL, entity_id INT DEFAULT NULL, changes LONGTEXT DEFAULT NULL, timestamp DATETIME NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, user_id INT NOT NULL, INDEX IDX_F6E1C0F5A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE intervention (id INT AUTO_INCREMENT NOT NULL, start_date DATETIME NOT NULL, end_date DATETIME DEFAULT NULL, notes LONGTEXT DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, signalement_id INT NOT NULL, INDEX IDX_D11814AB65C5E57E (signalement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE media (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, file_path VARCHAR(500) NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, signalement_id INT NOT NULL, INDEX IDX_6A2CA10C65C5E57E (signalement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL, `read` TINYINT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, user_id INT NOT NULL, signalement_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395 (user_id), INDEX IDX_BF5476CA65C5E57E (signalement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_57698A6A5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE signalement (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, priority VARCHAR(50) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, address VARCHAR(500) DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, user_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_F4B55114A76ED395 (user_id), INDEX IDX_F4B5511412469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_D11814AB65C5E57E FOREIGN KEY (signalement_id) REFERENCES signalement (id)');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10C65C5E57E FOREIGN KEY (signalement_id) REFERENCES signalement (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA65C5E57E FOREIGN KEY (signalement_id) REFERENCES signalement (id)');
        $this->addSql('ALTER TABLE signalement ADD CONSTRAINT FK_F4B55114A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE signalement ADD CONSTRAINT FK_F4B5511412469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE user ADD first_name VARCHAR(50) NOT NULL, ADD last_name VARCHAR(50) NOT NULL, ADD phone VARCHAR(20) NOT NULL, ADD user_role VARCHAR(50) NOT NULL, ADD is_active TINYINT DEFAULT 1 NOT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD created_by VARCHAR(255) DEFAULT NULL, ADD updated_by VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_log DROP FOREIGN KEY FK_F6E1C0F5A76ED395');
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY FK_D11814AB65C5E57E');
        $this->addSql('ALTER TABLE media DROP FOREIGN KEY FK_6A2CA10C65C5E57E');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA65C5E57E');
        $this->addSql('ALTER TABLE signalement DROP FOREIGN KEY FK_F4B55114A76ED395');
        $this->addSql('ALTER TABLE signalement DROP FOREIGN KEY FK_F4B5511412469DE2');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE intervention');
        $this->addSql('DROP TABLE media');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE signalement');
        $this->addSql('ALTER TABLE user DROP first_name, DROP last_name, DROP phone, DROP user_role, DROP is_active, DROP created_at, DROP updated_at, DROP created_by, DROP updated_by');
    }
}
