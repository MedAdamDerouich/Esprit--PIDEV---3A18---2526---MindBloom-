<?php
// Script de test rapide des routes événements

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

echo "========================================\n";
echo "TEST ROUTES EVENEMENTS - MINDBLOOM\n";
echo "========================================\n\n";

// Test 1: Fichiers
echo "[1] Vérification des fichiers...\n";
$files = [
    'src/Controller/EventController.php',
    'src/Controller/EventPatientController.php',
    'src/Entity/Event.php',
    'src/Entity/Participation.php',
    'templates/admin/event/index.html.twig',
    'templates/patient/event/index.html.twig',
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "  ✓ $file\n";
    } else {
        echo "  ✗ MANQUANT: $file\n";
    }
}

// Test 2: Base de données
echo "\n[2] Vérification connexion base de données...\n";
try {
    $dsn = $_ENV['DATABASE_URL'] ?? 'mysql://root:@127.0.0.1:3306/test';
    preg_match('/mysql:\/\/([^:]+):([^@]*)@([^:]+):(\d+)\/(.+)/', $dsn, $matches);
    
    if ($matches) {
        [, $user, $pass, $host, $port, $db] = $matches;
        echo "  Host: $host:$port\n";
        echo "  Database: $db\n";
        echo "  User: $user\n";
        
        $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass);
        echo "  ✓ Connexion MySQL OK\n";
        
        // Vérifier si la base existe
        $stmt = $pdo->query("SHOW DATABASES LIKE '$db'");
        if ($stmt->rowCount() > 0) {
            echo "  ✓ Base de données '$db' existe\n";
            
            // Se connecter à la base
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
            
            // Vérifier les tables
            $stmt = $pdo->query("SHOW TABLES LIKE 'evenement'");
            if ($stmt->rowCount() > 0) {
                echo "  ✓ Table 'evenement' existe\n";
                
                // Compter les événements
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM evenement");
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo "  → $count événement(s) dans la base\n";
            } else {
                echo "  ✗ Table 'evenement' N'EXISTE PAS\n";
                echo "  → Exécutez: php bin/console doctrine:migrations:migrate\n";
            }
            
            $stmt = $pdo->query("SHOW TABLES LIKE 'participation'");
            if ($stmt->rowCount() > 0) {
                echo "  ✓ Table 'participation' existe\n";
            } else {
                echo "  ✗ Table 'participation' N'EXISTE PAS\n";
            }
        } else {
            echo "  ✗ Base de données '$db' N'EXISTE PAS\n";
            echo "  → Créez la base: php bin/console doctrine:database:create\n";
        }
    }
} catch (Exception $e) {
    echo "  ✗ ERREUR: " . $e->getMessage() . "\n";
}

// Test 3: Routes
echo "\n[3] Chargement des routes...\n";
if (file_exists('config/routes.yaml')) {
    echo "  ✓ config/routes.yaml existe\n";
    $routes = file_get_contents('config/routes.yaml');
    if (strpos($routes, 'type: attribute') !== false) {
        echo "  ✓ Type 'attribute' configuré\n";
    } else {
        echo "  ✗ Type 'attribute' non configuré\n";
    }
}

echo "\n========================================\n";
echo "ACTIONS A FAIRE:\n";
echo "========================================\n";
echo "1. Si la base n'existe pas:\n";
echo "   php bin/console doctrine:database:create\n\n";
echo "2. Si les tables n'existent pas:\n";
echo "   php bin/console doctrine:migrations:migrate\n\n";
echo "3. Vider le cache:\n";
echo "   php bin/console cache:clear\n\n";
echo "4. Tester l'accès:\n";
echo "   Admin:   http://localhost:8000/admin/evenements\n";
echo "   Patient: http://localhost:8000/patient/evenements\n";
echo "\n";
