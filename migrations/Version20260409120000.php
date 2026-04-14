<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add google_id column to users table and make password nullable (Google OAuth support)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users MODIFY password VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD google_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E976F5C865 ON users (google_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_1483A5E976F5C865 ON users');
        $this->addSql('ALTER TABLE users DROP google_id');
        $this->addSql('ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL');
    }
}
