<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260417100921 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reponse CHANGE id_reponse id_reponse INT AUTO_INCREMENT NOT NULL, CHANGE texte_reponse texte_reponse LONGTEXT NOT NULL, ADD PRIMARY KEY (id_reponse)');
        $this->addSql('ALTER TABLE reponse ADD CONSTRAINT FK_5FB6DEC7E62CA5DB FOREIGN KEY (id_question) REFERENCES question (id_question)');
        $this->addSql('CREATE INDEX IDX_5FB6DEC7E62CA5DB ON reponse (id_question)');
        $this->addSql('ALTER TABLE test DROP FOREIGN KEY fk_test_psychologue');
        $this->addSql('ALTER TABLE test CHANGE nom_test nom_test VARCHAR(255) NOT NULL, CHANGE categorie categorie VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE test ADD CONSTRAINT FK_D87F7E0CCED9C570 FOREIGN KEY (id_psychologue) REFERENCES users (id)');
        $this->addSql('ALTER TABLE test RENAME INDEX fk_test_psychologue TO IDX_D87F7E0CCED9C570');
        $this->addSql('ALTER TABLE transactions CHANGE transaction_date transaction_date DATETIME NOT NULL');
        $this->addSql('ALTER TABLE transactions RENAME INDEX user_id TO IDX_EAA81A4CA76ED395');
        $this->addSql('DROP INDEX idx_email ON users');
        $this->addSql('DROP INDEX idx_username ON users');
        $this->addSql('DROP INDEX idx_role ON users');
        $this->addSql('DROP INDEX idx_status ON users');
        $this->addSql('ALTER TABLE users CHANGE role role ENUM(\'PATIENT\',\'PSYCHOLOGUE\',\'ADMIN\') DEFAULT \'PATIENT\', CHANGE status status ENUM(\'ACTIVE\',\'INACTIVE\',\'SUSPENDED\',\'PENDING\') DEFAULT \'ACTIVE\', CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE is_email_valid is_email_valid TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE users RENAME INDEX email TO UNIQ_1483A5E9E7927C74');
        $this->addSql('ALTER TABLE users RENAME INDEX username TO UNIQ_1483A5E9F85E0677');
        $this->addSql('DROP INDEX idx_user_id ON wallets');
        $this->addSql('ALTER TABLE wallets CHANGE balance balance NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(20) DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE wallets RENAME INDEX unique_user_wallet TO UNIQ_967AAA6CA76ED395');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reponse MODIFY id_reponse INT NOT NULL');
        $this->addSql('ALTER TABLE reponse DROP FOREIGN KEY FK_5FB6DEC7E62CA5DB');
        $this->addSql('DROP INDEX IDX_5FB6DEC7E62CA5DB ON reponse');
        $this->addSql('DROP INDEX `primary` ON reponse');
        $this->addSql('ALTER TABLE reponse CHANGE id_reponse id_reponse INT NOT NULL, CHANGE texte_reponse texte_reponse VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE test DROP FOREIGN KEY FK_D87F7E0CCED9C570');
        $this->addSql('ALTER TABLE test CHANGE nom_test nom_test VARCHAR(100) NOT NULL, CHANGE categorie categorie VARCHAR(255) DEFAULT \'Autre\' NOT NULL');
        $this->addSql('ALTER TABLE test ADD CONSTRAINT fk_test_psychologue FOREIGN KEY (id_psychologue) REFERENCES users (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE test RENAME INDEX idx_d87f7e0cced9c570 TO fk_test_psychologue');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CA76ED395');
        $this->addSql('ALTER TABLE transactions CHANGE transaction_date transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE transactions RENAME INDEX idx_eaa81a4ca76ed395 TO user_id');
        $this->addSql('ALTER TABLE users CHANGE role role VARCHAR(255) DEFAULT \'PATIENT\' NOT NULL, CHANGE status status VARCHAR(255) DEFAULT \'ACTIVE\', CHANGE is_email_valid is_email_valid TINYINT(1) DEFAULT 0, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('CREATE INDEX idx_email ON users (email)');
        $this->addSql('CREATE INDEX idx_username ON users (username)');
        $this->addSql('CREATE INDEX idx_role ON users (role)');
        $this->addSql('CREATE INDEX idx_status ON users (status)');
        $this->addSql('ALTER TABLE users RENAME INDEX uniq_1483a5e9e7927c74 TO email');
        $this->addSql('ALTER TABLE users RENAME INDEX uniq_1483a5e9f85e0677 TO username');
        $this->addSql('ALTER TABLE wallets CHANGE balance balance NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'ACTIVE\', CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('CREATE INDEX idx_user_id ON wallets (user_id)');
        $this->addSql('ALTER TABLE wallets RENAME INDEX uniq_967aaa6ca76ed395 TO unique_user_wallet');
    }
}
