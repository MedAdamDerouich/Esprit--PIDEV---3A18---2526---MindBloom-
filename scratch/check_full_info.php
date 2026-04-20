<?php

require __DIR__.'/../vendor/autoload.php';
(new \Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__.'/../.env');

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
$connection = $em->getConnection();

$sql = "SELECT id_facture, statut_livraison, id_user FROM facture";
$stmt = $connection->executeQuery($sql);
$results = $stmt->fetchAllAssociative();

echo "Detailed status list:\n";
foreach ($results as $row) {
    echo "ID: " . $row['id_facture'] . " | UserID: " . ($row['id_user'] ?? 'NULL') . " | Status: '" . $row['statut_livraison'] . "'\n";
}

$kernel->shutdown();
