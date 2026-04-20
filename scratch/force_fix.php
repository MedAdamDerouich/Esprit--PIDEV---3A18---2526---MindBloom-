<?php

require __DIR__.'/../vendor/autoload.php';
(new \Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__.'/../.env');

use App\Kernel;
use App\Entity\Facture;

$kernel = new Kernel('dev', true);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
$repo = $em->getRepository(Facture::class);

$ids = [18, 20, 22, 23, 24, 25, 27];
$count = 0;

foreach ($ids as $id) {
    echo "Processing ID $id...\n";
    $facture = $repo->find($id);
    if ($facture) {
        echo "Found Facture $id. Current status: '" . $facture->getStatutLivraison() . "'\n";
        $facture->setStatutLivraison(Facture::STATUS_CANCELLED);
        $em->persist($facture);
        $em->flush();
        echo "Flushed ID $id.\n";
        $count++;
    } else {
        echo "Facture $id NOT found via repo.\n";
    }
}

$kernel->shutdown();
