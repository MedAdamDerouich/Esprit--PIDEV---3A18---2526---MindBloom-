<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour créer les tables evenement et participation
 */
final class Version20260403145300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des tables evenement et participation pour le module événements';
    }

    public function up(Schema $schema): void
    {
        // Table evenement - créer seulement si elle n'existe pas
        if (!$schema->hasTable('evenement')) {
            $this->addSql('CREATE TABLE evenement (
                id_evenement INT AUTO_INCREMENT NOT NULL,
                titre VARCHAR(255) NOT NULL,
                description LONGTEXT DEFAULT NULL,
                date_debut DATETIME NOT NULL,
                date_fin DATETIME DEFAULT NULL,
                lieu VARCHAR(255) DEFAULT NULL,
                capacite_max INT DEFAULT NULL,
                organisateur VARCHAR(255) DEFAULT NULL,
                date_creation DATETIME NOT NULL,
                statut VARCHAR(50) NOT NULL,
                photo VARCHAR(255) DEFAULT NULL,
                prix DOUBLE PRECISION DEFAULT NULL,
                PRIMARY KEY(id_evenement)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        // Table participation - créer seulement si elle n'existe pas
        if (!$schema->hasTable('participation')) {
            $this->addSql('CREATE TABLE participation (
                id_participation INT AUTO_INCREMENT NOT NULL,
                id_evenement INT NOT NULL,
                id_user INT NOT NULL,
                date_inscription DATETIME NOT NULL,
                statut VARCHAR(50) NOT NULL,
                qr_code VARCHAR(255) DEFAULT NULL,
                commentaire LONGTEXT DEFAULT NULL,
                PRIMARY KEY(id_participation),
                INDEX IDX_AB55E24B7C76E4C7 (id_evenement),
                INDEX IDX_AB55E24B6B3CA4B (id_user),
                UNIQUE INDEX unique_event_user (id_evenement, id_user)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            
            // Foreign keys
            $this->addSql('ALTER TABLE participation 
                ADD CONSTRAINT FK_AB55E24B7C76E4C7 
                FOREIGN KEY (id_evenement) 
                REFERENCES evenement (id_evenement) 
                ON DELETE CASCADE');

            $this->addSql('ALTER TABLE participation 
                ADD CONSTRAINT FK_AB55E24B6B3CA4B 
                FOREIGN KEY (id_user) 
                REFERENCES users (id) 
                ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24B7C76E4C7');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24B6B3CA4B');
        $this->addSql('DROP TABLE participation');
        $this->addSql('DROP TABLE evenement');
    }
}
