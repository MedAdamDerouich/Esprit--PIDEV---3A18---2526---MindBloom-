<?php

require __DIR__.'/../vendor/autoload.php';
(new \Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__.'/../.env');

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
$connection = $em->getConnection();

echo "Starting fix...\n";

$sql = "UPDATE facture SET statut_livraison = 'ANNULE' WHERE statut_livraison = '' OR statut_livraison IS NULL";
try {
    $affectedRows = $connection->executeStatement($sql);
    echo "Affected rows: $affectedRows\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Verification
$sqlCheck = "SELECT id_facture, statut_livraison FROM facture WHERE statut_livraison = 'ANNULE'";
$results = $connection->executeQuery($sqlCheck)->fetchAllAssociative();
echo "Orders now marked as 'ANNULE': " . count($results) . "\n";

$kernel->shutdown();
