<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405084853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE resultat_test (id INT AUTO_INCREMENT NOT NULL, test_id INT NOT NULL, patient_id INT DEFAULT NULL, score INT NOT NULL, commentaire VARCHAR(255) DEFAULT NULL, etat VARCHAR(50) DEFAULT NULL, INDEX IDX_7ECAF22D1E5D0459 (test_id), INDEX IDX_7ECAF22D6B899279 (patient_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE resultat_test ADD CONSTRAINT FK_7ECAF22D1E5D0459 FOREIGN KEY (test_id) REFERENCES test (id)');
        $this->addSql('ALTER TABLE resultat_test ADD CONSTRAINT FK_7ECAF22D6B899279 FOREIGN KEY (patient_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE question MODIFY id_question INT NOT NULL');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY question_ibfk_1');
        $this->addSql('DROP INDEX id_test ON question');
        $this->addSql('DROP INDEX `primary` ON question');
        $this->addSql('ALTER TABLE question CHANGE texte_question texte_question LONGTEXT NOT NULL, CHANGE id_question id INT AUTO_INCREMENT NOT NULL, CHANGE id_test test_id INT NOT NULL');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E1E5D0459 FOREIGN KEY (test_id) REFERENCES test (id)');
        $this->addSql('CREATE INDEX IDX_B6F7494E1E5D0459 ON question (test_id)');
        $this->addSql('ALTER TABLE question ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE reponse MODIFY id_reponse INT NOT NULL');
        $this->addSql('ALTER TABLE reponse DROP FOREIGN KEY reponse_ibfk_1');
        $this->addSql('DROP INDEX id_question ON reponse');
        $this->addSql('DROP INDEX `primary` ON reponse');
        $this->addSql('ALTER TABLE reponse CHANGE texte_reponse texte_reponse LONGTEXT NOT NULL, CHANGE id_reponse id INT AUTO_INCREMENT NOT NULL, CHANGE id_question question_id INT NOT NULL');
        $this->addSql('ALTER TABLE reponse ADD CONSTRAINT FK_5FB6DEC71E27F6BF FOREIGN KEY (question_id) REFERENCES question (id)');
        $this->addSql('CREATE INDEX IDX_5FB6DEC71E27F6BF ON reponse (question_id)');
        $this->addSql('ALTER TABLE reponse ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE test MODIFY id_test INT NOT NULL');
        $this->addSql('ALTER TABLE test DROP FOREIGN KEY fk_test_psychologue');
        $this->addSql('DROP INDEX fk_test_psychologue ON test');
        $this->addSql('DROP INDEX `primary` ON test');
        $this->addSql('ALTER TABLE test CHANGE nom_test nom_test VARCHAR(255) NOT NULL, CHANGE categorie categorie VARCHAR(50) NOT NULL, CHANGE id_test id INT AUTO_INCREMENT NOT NULL, CHANGE id_psychologue user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE test ADD CONSTRAINT FK_D87F7E0CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('CREATE INDEX IDX_D87F7E0CA76ED395 ON test (user_id)');
        $this->addSql('ALTER TABLE test ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE transactions CHANGE transaction_date transaction_date DATETIME NOT NULL');
        $this->addSql('ALTER TABLE transactions RENAME INDEX user_id TO IDX_EAA81A4CA76ED395');
        $this->addSql('DROP INDEX idx_email ON users');
        $this->addSql('DROP INDEX idx_role ON users');
        $this->addSql('DROP INDEX idx_status ON users');
        $this->addSql('DROP INDEX idx_username ON users');
        $this->addSql('ALTER TABLE users CHANGE role role ENUM(\'PATIENT\',\'PSYCHOLOGUE\',\'ADMIN\') DEFAULT \'PATIENT\', CHANGE status status ENUM(\'ACTIVE\',\'INACTIVE\',\'SUSPENDED\') DEFAULT \'ACTIVE\', CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE is_email_valid is_email_valid TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE users RENAME INDEX email TO UNIQ_1483A5E9E7927C74');
        $this->addSql('ALTER TABLE users RENAME INDEX username TO UNIQ_1483A5E9F85E0677');
        $this->addSql('DROP INDEX idx_user_id ON wallets');
        $this->addSql('ALTER TABLE wallets CHANGE balance balance NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(20) DEFAULT NULL, CHANGE currency currency VARCHAR(3) NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE wallets RENAME INDEX unique_user_wallet TO UNIQ_967AAA6CA76ED395');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE resultat_test DROP FOREIGN KEY FK_7ECAF22D1E5D0459');
        $this->addSql('ALTER TABLE resultat_test DROP FOREIGN KEY FK_7ECAF22D6B899279');
        $this->addSql('DROP TABLE resultat_test');
        $this->addSql('ALTER TABLE question MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E1E5D0459');
        $this->addSql('DROP INDEX IDX_B6F7494E1E5D0459 ON question');
        $this->addSql('DROP INDEX `PRIMARY` ON question');
        $this->addSql('ALTER TABLE question CHANGE texte_question texte_question VARCHAR(255) NOT NULL, CHANGE id id_question INT AUTO_INCREMENT NOT NULL, CHANGE test_id id_test INT NOT NULL');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT question_ibfk_1 FOREIGN KEY (id_test) REFERENCES test (id_test) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('CREATE INDEX id_test ON question (id_test)');
        $this->addSql('ALTER TABLE question ADD PRIMARY KEY (id_question)');
        $this->addSql('ALTER TABLE reponse MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE reponse DROP FOREIGN KEY FK_5FB6DEC71E27F6BF');
        $this->addSql('DROP INDEX IDX_5FB6DEC71E27F6BF ON reponse');
        $this->addSql('DROP INDEX `PRIMARY` ON reponse');
        $this->addSql('ALTER TABLE reponse CHANGE texte_reponse texte_reponse VARCHAR(50) NOT NULL, CHANGE id id_reponse INT AUTO_INCREMENT NOT NULL, CHANGE question_id id_question INT NOT NULL');
        $this->addSql('ALTER TABLE reponse ADD CONSTRAINT reponse_ibfk_1 FOREIGN KEY (id_question) REFERENCES question (id_question) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('CREATE INDEX id_question ON reponse (id_question)');
        $this->addSql('ALTER TABLE reponse ADD PRIMARY KEY (id_reponse)');
        $this->addSql('ALTER TABLE test MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE test DROP FOREIGN KEY FK_D87F7E0CA76ED395');
        $this->addSql('DROP INDEX IDX_D87F7E0CA76ED395 ON test');
        $this->addSql('DROP INDEX `PRIMARY` ON test');
        $this->addSql('ALTER TABLE test CHANGE nom_test nom_test VARCHAR(100) NOT NULL, CHANGE categorie categorie VARCHAR(255) DEFAULT \'Autre\' NOT NULL, CHANGE id id_test INT AUTO_INCREMENT NOT NULL, CHANGE user_id id_psychologue INT DEFAULT NULL');
        $this->addSql('ALTER TABLE test ADD CONSTRAINT fk_test_psychologue FOREIGN KEY (id_psychologue) REFERENCES users (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('CREATE INDEX fk_test_psychologue ON test (id_psychologue)');
        $this->addSql('ALTER TABLE test ADD PRIMARY KEY (id_test)');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CA76ED395');
        $this->addSql('ALTER TABLE transactions CHANGE transaction_date transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE transactions RENAME INDEX idx_eaa81a4ca76ed395 TO user_id');
        $this->addSql('ALTER TABLE users CHANGE role role VARCHAR(255) DEFAULT \'PATIENT\' NOT NULL, CHANGE status status VARCHAR(255) DEFAULT \'ACTIVE\' NOT NULL, CHANGE is_email_valid is_email_valid TINYINT(1) DEFAULT 0, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('CREATE INDEX idx_email ON users (email)');
        $this->addSql('CREATE INDEX idx_role ON users (role)');
        $this->addSql('CREATE INDEX idx_status ON users (status)');
        $this->addSql('CREATE INDEX idx_username ON users (username)');
        $this->addSql('ALTER TABLE users RENAME INDEX uniq_1483a5e9e7927c74 TO email');
        $this->addSql('ALTER TABLE users RENAME INDEX uniq_1483a5e9f85e0677 TO username');
        $this->addSql('ALTER TABLE wallets CHANGE balance balance NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'ACTIVE\', CHANGE currency currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('CREATE INDEX idx_user_id ON wallets (user_id)');
        $this->addSql('ALTER TABLE wallets RENAME INDEX uniq_967aaa6ca76ed395 TO unique_user_wallet');
    }
}
