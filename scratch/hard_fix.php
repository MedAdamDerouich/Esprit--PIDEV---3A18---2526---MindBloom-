<?php

require __DIR__.'/../vendor/autoload.php';
(new \Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__.'/../.env');

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
$connection = $em->getConnection();

echo "Running hard fix...\n";
$connection->beginTransaction();
try {
    $affected = $connection->executeStatement("UPDATE facture SET statut_livraison = 'ANNULE' WHERE statut_livraison = '' OR statut_livraison IS NULL");
    $connection->commit();
    echo "Affected: $affected\n";
} catch (\Exception $e) {
    $connection->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}

$kernel->shutdown();
