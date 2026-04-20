<?php

require __DIR__.'/../vendor/autoload.php';
(new \Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__.'/../.env');

use App\Kernel;
use App\Entity\Facture;

$kernel = new Kernel('dev', true);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
$repo = $em->getRepository(Facture::class);

$factures = $repo->findAll();
$count = 0;

foreach ($factures as $facture) {
    $status = $facture->getStatutLivraison();
    if ($status === null || trim($status) === '') {
        $facture->setStatutLivraison(Facture::STATUS_CANCELLED);
        $count++;
    }
}

$em->flush();

echo "Fixed $count orders using Entity Manager.\n";

$kernel->shutdown();
