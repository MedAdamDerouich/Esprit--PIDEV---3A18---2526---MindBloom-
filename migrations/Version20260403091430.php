<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403091430 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        
        // Créer table transactions seulement si elle n'existe pas
        if (!$schema->hasTable('transactions')) {
            $this->addSql('CREATE TABLE transactions (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, type VARCHAR(50) NOT NULL, amount DOUBLE PRECISION NOT NULL, description VARCHAR(255) DEFAULT NULL, transaction_date DATETIME NOT NULL, INDEX IDX_EAA81A4CA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        
        // Créer table users seulement si elle n'existe pas
        if (!$schema->hasTable('users')) {
            $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, full_name VARCHAR(255) NOT NULL, email VARCHAR(100) NOT NULL, username VARCHAR(50) NOT NULL, role VARCHAR(20) DEFAULT \'PATIENT\' NOT NULL, password VARCHAR(255) NOT NULL, phone VARCHAR(20) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, date_of_birth DATE DEFAULT NULL, profile_image VARCHAR(255) DEFAULT NULL, status VARCHAR(20) DEFAULT \'ACTIVE\' NOT NULL, is_email_valid TINYINT(1) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        
        // Créer table wallets seulement si elle n'existe pas
        if (!$schema->hasTable('wallets')) {
            $this->addSql('CREATE TABLE wallets (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, balance NUMERIC(10, 2) NOT NULL, last_recharge_date DATETIME DEFAULT NULL, status VARCHAR(20) DEFAULT NULL, currency VARCHAR(3) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_967AAA6CA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        
        // Ajouter les contraintes de clé étrangère si les tables existent
        if ($schema->hasTable('transactions') && $schema->hasTable('users')) {
            $transactionsTable = $schema->getTable('transactions');
            if (!$transactionsTable->hasForeignKey('FK_EAA81A4CA76ED395')) {
                $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
            }
        }
        
        if ($schema->hasTable('wallets') && $schema->hasTable('users')) {
            $walletsTable = $schema->getTable('wallets');
            if (!$walletsTable->hasForeignKey('FK_967AAA6CA76ED395')) {
                $this->addSql('ALTER TABLE wallets ADD CONSTRAINT FK_967AAA6CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CA76ED395');
        $this->addSql('ALTER TABLE wallets DROP FOREIGN KEY FK_967AAA6CA76ED395');
        $this->addSql('DROP TABLE transactions');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE wallets');
    }
}
