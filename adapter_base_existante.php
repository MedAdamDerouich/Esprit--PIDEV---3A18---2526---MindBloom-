<?php
// Script pour adapter la base existante au module événements Symfony

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

echo "========================================\n";
echo "ADAPTATION BASE EXISTANTE - EVENEMENTS\n";
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
    
    echo "[OK] Connexion reussie a la base '$db'\n\n";
    
    // Étape 1: Vérifier la table evenement
    echo "[1/4] Verification de la table evenement...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'evenement'");
    if ($stmt->rowCount() > 0) {
        echo "  [OK] Table 'evenement' existe\n";
        
        // Vérifier si date_creation est DATETIME ou TIMESTAMP
        $stmt = $pdo->query("SHOW COLUMNS FROM evenement LIKE 'date_creation'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (strtolower($col['Type']) == 'timestamp') {
            echo "  [INFO] Modification date_creation de TIMESTAMP a DATETIME...\n";
            $pdo->exec("ALTER TABLE evenement MODIFY date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
            echo "  [OK] date_creation modifiee\n";
        }
        
        // Vérifier si statut est compatible
        $stmt = $pdo->query("SHOW COLUMNS FROM evenement LIKE 'statut'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  [INFO] Statut actuel: " . $col['Type'] . "\n";
        
    } else {
        echo "  [ERREUR] Table 'evenement' n'existe pas!\n";
        exit(1);
    }
    
    // Étape 2: Adapter la table participation
    echo "\n[2/4] Adaptation de la table participation...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'participation'");
    
    if ($stmt->rowCount() > 0) {
        echo "  [OK] Table 'participation' existe\n";
        
        // Vérifier les colonnes existantes
        $stmt = $pdo->query("SHOW COLUMNS FROM participation");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }
        
        echo "  [INFO] Colonnes actuelles: " . implode(', ', $columns) . "\n";
        
        // Renommer id_utilisateur en id_user si nécessaire
        if (in_array('id_utilisateur', $columns) && !in_array('id_user', $columns)) {
            echo "  [INFO] Renommage id_utilisateur -> id_user...\n";
            $pdo->exec("ALTER TABLE participation CHANGE id_utilisateur id_user INT NOT NULL");
            echo "  [OK] Colonne renommee\n";
        }
        
        // Ajouter qr_code si manquant
        if (!in_array('qr_code', $columns)) {
            echo "  [INFO] Ajout du champ qr_code...\n";
            $pdo->exec("ALTER TABLE participation ADD COLUMN qr_code VARCHAR(255) DEFAULT NULL AFTER statut");
            echo "  [OK] qr_code ajoute\n";
        }
        
        // Renommer date_participation en date_inscription si nécessaire
        if (in_array('date_participation', $columns) && !in_array('date_inscription', $columns)) {
            echo "  [INFO] Renommage date_participation -> date_inscription...\n";
            $pdo->exec("ALTER TABLE participation CHANGE date_participation date_inscription DATETIME NOT NULL");
            echo "  [OK] Colonne renommee\n";
        } else if (!in_array('date_inscription', $columns)) {
            echo "  [INFO] Ajout du champ date_inscription...\n";
            $pdo->exec("ALTER TABLE participation ADD COLUMN date_inscription DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER id_user");
            echo "  [OK] date_inscription ajoute\n";
        }
        
        // Modifier statut pour qu'il soit compatible
        echo "  [INFO] Modification du champ statut...\n";
        $pdo->exec("ALTER TABLE participation MODIFY statut VARCHAR(50) NOT NULL DEFAULT 'inscrit'");
        echo "  [OK] statut modifie\n";
        
        // Vérifier et recréer la contrainte unique si nécessaire
        $stmt = $pdo->query("SHOW INDEXES FROM participation WHERE Key_name = 'unique_event_user'");
        if ($stmt->rowCount() == 0) {
            echo "  [INFO] Ajout de la contrainte unique (id_evenement, id_user)...\n";
            try {
                $pdo->exec("ALTER TABLE participation ADD UNIQUE INDEX unique_event_user (id_evenement, id_user)");
                echo "  [OK] Contrainte unique ajoutee\n";
            } catch (Exception $e) {
                echo "  [WARNING] Impossible d'ajouter la contrainte (peut-etre des doublons): " . $e->getMessage() . "\n";
            }
        }
        
        // Vérifier la clé étrangère vers users
        $stmt = $pdo->query("SELECT * FROM information_schema.KEY_COLUMN_USAGE 
                             WHERE TABLE_SCHEMA = '$db' 
                             AND TABLE_NAME = 'participation' 
                             AND COLUMN_NAME = 'id_user' 
                             AND REFERENCED_TABLE_NAME = 'users'");
        
        if ($stmt->rowCount() == 0) {
            echo "  [INFO] Ajout de la cle etrangere vers users...\n";
            try {
                $pdo->exec("ALTER TABLE participation 
                           ADD CONSTRAINT FK_AB55E24B6B3CA4B 
                           FOREIGN KEY (id_user) 
                           REFERENCES users (id) 
                           ON DELETE CASCADE");
                echo "  [OK] Cle etrangere ajoutee\n";
            } catch (Exception $e) {
                echo "  [WARNING] Impossible d'ajouter la FK: " . $e->getMessage() . "\n";
            }
        }
        
    } else {
        echo "  [ERREUR] Table 'participation' n'existe pas!\n";
        exit(1);
    }
    
    // Étape 3: Marquer les migrations comme exécutées
    echo "\n[3/4] Enregistrement des migrations...\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'doctrine_migration_versions'");
    if ($stmt->rowCount() > 0) {
        // Marquer la migration des événements comme exécutée
        $stmt = $pdo->prepare("SELECT * FROM doctrine_migration_versions WHERE version = ?");
        $stmt->execute(['DoctrineMigrations\\Version20260403145300']);
        
        if ($stmt->rowCount() == 0) {
            $pdo->exec("INSERT INTO doctrine_migration_versions (version, executed_at, execution_time) 
                        VALUES ('DoctrineMigrations\\\\Version20260403145300', NOW(), 1)");
            echo "  [OK] Migration evenements enregistree\n";
        } else {
            echo "  [INFO] Migration evenements deja enregistree\n";
        }
        
        // Marquer la migration problématique comme exécutée pour l'ignorer
        $stmt = $pdo->prepare("SELECT * FROM doctrine_migration_versions WHERE version = ?");
        $stmt->execute(['DoctrineMigrations\\Version20260403091430']);
        
        if ($stmt->rowCount() == 0) {
            $pdo->exec("INSERT INTO doctrine_migration_versions (version, executed_at, execution_time) 
                        VALUES ('DoctrineMigrations\\\\Version20260403091430', NOW(), 1)");
            echo "  [OK] Migration users enregistree (ignoree)\n";
        }
    }
    
    // Étape 4: Statistiques
    echo "\n[4/4] Statistiques...\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM evenement");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  -> $count evenement(s) dans la base\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM participation");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  -> $count participation(s) dans la base\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  -> $count utilisateur(s) dans la base\n";
    
    echo "\n========================================\n";
    echo "ADAPTATION TERMINEE AVEC SUCCES!\n";
    echo "========================================\n\n";
    
    echo "Prochaines etapes:\n";
    echo "1. Videz le cache:\n";
    echo "   php bin/console cache:clear\n\n";
    echo "2. Testez l'acces:\n";
    echo "   ADMIN:   http://localhost:8000/admin/evenements\n";
    echo "   PATIENT: http://localhost:8000/patient/evenements\n\n";
    
} catch (Exception $e) {
    echo "\n[ERREUR] " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
