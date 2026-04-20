<?php

require __DIR__.'/../vendor/autoload.php';
(new \Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__.'/../.env');

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
$connection = $em->getConnection();

$sql = "SELECT id_facture, HEX(statut_livraison) as hex_status, statut_livraison FROM facture WHERE id_facture = 18";
$result = $connection->executeQuery($sql)->fetchAssociative();

echo "ID 18 Hex Status: '" . $result['hex_status'] . "'\n";
echo "ID 18 Raw Status: '" . $result['statut_livraison'] . "'\n";

$kernel->shutdown();
