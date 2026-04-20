<?php

require __DIR__.'/../vendor/autoload.php';
(new \Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__.'/../.env');

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
$connection = $em->getConnection();

// Update empty statuses to ANNULE
$sql = "UPDATE facture SET statut_livraison = 'ANNULE' WHERE statut_livraison = '' OR statut_livraison IS NULL";
$affectedRows = $connection->executeStatement($sql);

echo "Fixed $affectedRows orders by setting status to 'ANNULE' where it was empty or null.\n";

$kernel->shutdown();
