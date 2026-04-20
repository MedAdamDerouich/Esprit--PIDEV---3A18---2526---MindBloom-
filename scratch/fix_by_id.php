<?php

require __DIR__.'/../vendor/autoload.php';
(new \Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__.'/../.env');

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
$connection = $em->getConnection();

$ids = [18, 20, 22, 23, 24, 25, 27];

foreach ($ids as $id) {
    echo "Updating ID $id...\n";
    $affected = $connection->executeStatement("UPDATE facture SET statut_livraison = 'ANNULE' WHERE id_facture = ?", [$id]);
    echo "Affected: $affected\n";
}

$kernel->shutdown();
