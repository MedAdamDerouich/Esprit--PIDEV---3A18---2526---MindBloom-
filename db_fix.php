<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=test', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
try { $pdo->exec("ALTER TABLE resultattest MODIFY id_resultat INT AUTO_INCREMENT"); echo "resultattest OK\n"; } catch(Exception $e) { echo $e->getMessage() . "\n"; }
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
