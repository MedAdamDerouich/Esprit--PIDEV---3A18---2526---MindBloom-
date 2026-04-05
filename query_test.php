<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=test', 'root', '');
$stmt = $pdo->query('SELECT id_test, nom_test, id_psychologue FROM test');
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($results);
