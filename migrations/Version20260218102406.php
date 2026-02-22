<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218102406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY `FK_9474526C65C5E57E`');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY `FK_9474526CF675F31B`');
        $this->addSql('ALTER TABLE favorite DROP FOREIGN KEY `FK_68C58ED965C5E57E`');
        $this->addSql('ALTER TABLE favorite DROP FOREIGN KEY `FK_68C58ED9A76ED395`');
        $this->addSql('ALTER TABLE rating DROP FOREIGN KEY `FK_D889262265C5E57E`');
        $this->addSql('ALTER TABLE rating DROP FOREIGN KEY `FK_D8892622A76ED395`');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE favorite');
        $this->addSql('DROP TABLE rating');
        $this->addSql('ALTER TABLE user ADD language VARCHAR(5) DEFAULT \'en\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, signalement_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_9474526C65C5E57E (signalement_id), INDEX IDX_9474526CF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE favorite (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, signalement_id INT NOT NULL, UNIQUE INDEX UNIQ_USER_SIGNALEMENT (user_id, signalement_id), INDEX IDX_68C58ED9A76ED395 (user_id), INDEX IDX_68C58ED965C5E57E (signalement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE rating (id INT AUTO_INCREMENT NOT NULL, score SMALLINT NOT NULL, comment LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INT NOT NULL, signalement_id INT NOT NULL, INDEX IDX_D889262265C5E57E (signalement_id), UNIQUE INDEX UNIQ_USER_SIGNALEMENT_RATING (user_id, signalement_id), INDEX IDX_D8892622A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT `FK_9474526C65C5E57E` FOREIGN KEY (signalement_id) REFERENCES signalement (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT `FK_9474526CF675F31B` FOREIGN KEY (author_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT `FK_68C58ED965C5E57E` FOREIGN KEY (signalement_id) REFERENCES signalement (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT `FK_68C58ED9A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT `FK_D889262265C5E57E` FOREIGN KEY (signalement_id) REFERENCES signalement (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT `FK_D8892622A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE user DROP language');
    }
}
