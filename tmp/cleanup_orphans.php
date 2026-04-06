<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

require dirname(__DIR__).'/vendor/autoload.php';

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

$entityManager = $kernel->getContainer()->get('doctrine')->getManager();
$conn = $entityManager->getConnection();

// Find orphans
$orphans = $conn->iterateAssociative("SELECT id_feedback FROM feedback f LEFT JOIN users u ON f.id_user = u.id WHERE u.id IS NULL AND f.id_user IS NOT NULL");

$idsToDelete = [];
foreach ($orphans as $orphan) {
    $idsToDelete[] = $orphan['id_feedback'];
}

if (!empty($idsToDelete)) {
    echo "Deleting orphaned feedbacks: " . implode(', ', $idsToDelete) . "\n";
    $conn->executeStatement("DELETE FROM feedback WHERE id_feedback IN (" . implode(',', $idsToDelete) . ")");
} else {
    echo "No orphaned feedbacks found.\n";
}

$kernel->shutdown();
