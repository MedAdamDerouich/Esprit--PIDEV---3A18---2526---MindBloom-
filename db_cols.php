<?php
$dbFile = __DIR__ . '/var/data.db'; // Assuming there is no SQLite since env points to MySQL
// The issue with the previous PHP script was that it didn't find the .env file vars properly because it was trying to boot the kernel.
// Let's do it using raw PDO.
$env = file_get_contents('.env');
preg_match('/DATABASE_URL="(.*)"/', $env, $matches);
$dsnStr = $matches[1];
// parse 'mysql://root:@127.0.0.1:3306/test'
// To simple PDO format if it's MySQL:
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=test', 'root', '');

function printTableCols($pdo, $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        echo "Table $table Columns: ";
        foreach($stmt as $row) {
            echo $row['Field'] . " ";
        }
        echo "\n";
    } catch (Exception $e) {}
}

printTableCols($pdo, 'test');
printTableCols($pdo, 'question');
printTableCols($pdo, 'reponse');
printTableCols($pdo, 'resultat_test');
