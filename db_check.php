<?php
require 'vendor/autoload.php';
$kernel = new App\Kernel('dev', true);
$kernel->boot();
$conn = $kernel->getContainer()->get('doctrine.dbal.default_connection');

function printCols($conn, $table) {
    echo "TABLE: $table\n";
    foreach ($conn->fetchAllAssociative("SHOW COLUMNS FROM $table") as $col) {
        echo $col['Field']." ";
    }
    echo "\n";
}

printCols($conn, 'test');
printCols($conn, 'question');
printCols($conn, 'reponse');
printCols($conn, 'resultat_test');
