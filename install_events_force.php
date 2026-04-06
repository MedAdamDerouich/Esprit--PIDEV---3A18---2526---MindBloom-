<?php
// Script pour forcer la migration des événements en ignorant l'ancienne migration problématique

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

echo "========================================\n";
echo "INSTALLATION FORCEE MODULE EVENEMENTS\n";
echo "========================================\n\n";

try {
    // Connexion à la base
    $dsn = $_ENV['DATABASE_URL'] ?? 'mysql://root:@127.0.0.1:3306/test';
    preg_match('/mysql:\/\/([^:]+):([^@]*)@([^:]+):(\d+)\/(.+)/', $dsn, $matches);
    
    if (!$matches) {
        die("Erreur: DATABASE_URL invalide\n");
    }
    
    [, $user, $pass, $host, $port, $db] = $matches;
    
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "[1/4] Connexion reussie a la base '$db'\n\n";
    
    // Vérifier si table evenement existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'evenement'");
    
    if ($stmt->rowCount() > 0) {
        echo "[2/4] Table 'evenement' existe deja\n";
    } else {
        echo "[2/4] Creation de la table 'evenement'...\n";
        $pdo->exec("CREATE TABLE evenement (
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
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        echo "    [OK] Table 'evenement' creee\n";
    }
    
    // Vérifier si table participation existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'participation'");
    
    if ($stmt->rowCount() > 0) {
        echo "[3/4] Table 'participation' existe deja\n";
    } else {
        echo "[3/4] Creation de la table 'participation'...\n";
        $pdo->exec("CREATE TABLE participation (
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
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        echo "    [OK] Table 'participation' creee\n";
        
        echo "    Creation des cles etrangeres...\n";
        $pdo->exec("ALTER TABLE participation 
            ADD CONSTRAINT FK_AB55E24B7C76E4C7 
            FOREIGN KEY (id_evenement) 
            REFERENCES evenement (id_evenement) 
            ON DELETE CASCADE");
        
        $pdo->exec("ALTER TABLE participation 
            ADD CONSTRAINT FK_AB55E24B6B3CA4B 
            FOREIGN KEY (id_user) 
            REFERENCES users (id) 
            ON DELETE CASCADE");
        echo "    [OK] Cles etrangeres creees\n";
    }
    
    // Marquer la migration comme exécutée
    echo "\n[4/4] Enregistrement de la migration...\n";
    
    // Vérifier si la table doctrine_migration_versions existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'doctrine_migration_versions'");
    
    if ($stmt->rowCount() > 0) {
        // Vérifier si Version20260403145300 est déjà enregistrée
        $stmt = $pdo->prepare("SELECT * FROM doctrine_migration_versions WHERE version = ?");
        $stmt->execute(['DoctrineMigrations\\Version20260403145300']);
        
        if ($stmt->rowCount() == 0) {
            $pdo->exec("INSERT INTO doctrine_migration_versions (version, executed_at, execution_time) 
                        VALUES ('DoctrineMigrations\\\\Version20260403145300', NOW(), 1)");
            echo "    [OK] Migration enregistree\n";
        } else {
            echo "    [INFO] Migration deja enregistree\n";
        }
    }
    
    echo "\n========================================\n";
    echo "INSTALLATION TERMINEE AVEC SUCCES!\n";
    echo "========================================\n\n";
    
    // Afficher les tables
    echo "Tables dans la base de donnees:\n";
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "  - " . $row[0] . "\n";
    }
    
    echo "\nVous pouvez maintenant acceder aux evenements:\n";
    echo "  ADMIN:   http://localhost:8000/admin/evenements\n";
    echo "  PATIENT: http://localhost:8000/patient/evenements\n\n";
    
    echo "N'oubliez pas de vider le cache:\n";
    echo "  php bin/console cache:clear\n\n";
    
} catch (Exception $e) {
    echo "\n[ERREUR] " . $e->getMessage() . "\n";
    exit(1);
}
