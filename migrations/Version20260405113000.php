<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize empty participation status values to inscrit';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE participation SET statut = 'inscrit' WHERE statut IS NULL OR TRIM(statut) = ''");
    }

    public function down(Schema $schema): void
    {
        // Irreversible data cleanup migration.
    }
}
