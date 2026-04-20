<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

require __DIR__.'/../vendor/autoload.php';

(new \Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel('dev', true);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
$connection = $em->getConnection();

$sql = "SELECT DISTINCT statut_livraison FROM facture";
$stmt = $connection->executeQuery($sql);
$results = $stmt->fetchAllAssociative();

echo "Distinct status values in 'facture' table:\n";
foreach ($results as $row) {
    echo "- '" . $row['statut_livraison'] . "'\n";
}

$sqlCount = "SELECT COUNT(*) as total FROM facture";
$resultsCount = $connection->executeQuery($sqlCount)->fetchAssociative();
echo "\nTotal orders: " . $resultsCount['total'] . "\n";

$kernel->shutdown();
