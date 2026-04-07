<?php
$dbUser = 'root';
$dbPass = '';
$dsn = 'mysql:host=127.0.0.1;dbname=test;charset=utf8mb4';
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
    
    $queries = [
        "ALTER TABLE question MODIFY id_question INT NOT NULL",
        "ALTER TABLE question DROP FOREIGN KEY question_ibfk_1",
        "DROP INDEX id_test ON question",
        "ALTER TABLE question CHANGE texte_question texte_question LONGTEXT NOT NULL, CHANGE id_question id INT AUTO_INCREMENT NOT NULL, CHANGE id_test test_id INT NOT NULL",
        "ALTER TABLE question ADD CONSTRAINT FK_B6F7494E1E5D0459 FOREIGN KEY (test_id) REFERENCES test (id)",
        "CREATE INDEX IDX_B6F7494E1E5D0459 ON question (test_id)",
        "ALTER TABLE question DROP PRIMARY KEY, ADD PRIMARY KEY (id)",
        
        "ALTER TABLE reponse MODIFY id_reponse INT NOT NULL",
        "ALTER TABLE reponse DROP FOREIGN KEY reponse_ibfk_1",
        "DROP INDEX id_question ON reponse",
        "ALTER TABLE reponse CHANGE texte_reponse texte_reponse LONGTEXT NOT NULL, CHANGE id_reponse id INT AUTO_INCREMENT NOT NULL, CHANGE id_question question_id INT NOT NULL",
        "ALTER TABLE reponse ADD CONSTRAINT FK_5FB6DEC71E27F6BF FOREIGN KEY (question_id) REFERENCES question (id)",
        "CREATE INDEX IDX_5FB6DEC71E27F6BF ON reponse (question_id)",
        "ALTER TABLE reponse DROP PRIMARY KEY, ADD PRIMARY KEY (id)",
        
        "ALTER TABLE test MODIFY id_test INT NOT NULL",
        "ALTER TABLE test DROP FOREIGN KEY fk_test_psychologue",
        "DROP INDEX fk_test_psychologue ON test",
        "ALTER TABLE test CHANGE nom_test nom_test VARCHAR(255) NOT NULL, CHANGE categorie categorie VARCHAR(50) NOT NULL, CHANGE id_test id INT AUTO_INCREMENT NOT NULL, CHANGE id_psychologue user_id INT DEFAULT NULL",
        "ALTER TABLE test ADD CONSTRAINT FK_D87F7E0CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)",
        "CREATE INDEX IDX_D87F7E0CA76ED395 ON test (user_id)",
        "ALTER TABLE test DROP PRIMARY KEY, ADD PRIMARY KEY (id)",
        
        "ALTER TABLE resultat_test ADD CONSTRAINT FK_7ECAF22D1E5D0459 FOREIGN KEY (test_id) REFERENCES test (id)",
        "ALTER TABLE resultat_test ADD CONSTRAINT FK_7ECAF22D6B899279 FOREIGN KEY (patient_id) REFERENCES users (id)",

        "ALTER TABLE transactions CHANGE transaction_date transaction_date DATETIME NOT NULL",
        "ALTER TABLE transactions RENAME INDEX user_id TO IDX_EAA81A4CA76ED395",
        
        "ALTER TABLE users CHANGE role role ENUM('PATIENT','PSYCHOLOGUE','ADMIN') DEFAULT 'PATIENT', CHANGE status status ENUM('ACTIVE','INACTIVE','SUSPENDED') DEFAULT 'ACTIVE', CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE is_email_valid is_email_valid TINYINT(1) DEFAULT NULL",
        "ALTER TABLE users RENAME INDEX email TO UNIQ_1483A5E9E7927C74",
        "ALTER TABLE users RENAME INDEX username TO UNIQ_1483A5E9F85E0677",
        
        "ALTER TABLE wallets CHANGE balance balance NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(20) DEFAULT NULL, CHANGE currency currency VARCHAR(3) NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL",
        "ALTER TABLE wallets RENAME INDEX unique_user_wallet TO UNIQ_967AAA6CA76ED395",
    ];

    foreach ($queries as $q) {
        try {
            $pdo->exec($q);
            echo "SUCCESS: $q\n";
        } catch(Exception $e) {
            echo "SKIPPED/ERROR: $q -> " . $e->getMessage() . "\n";
        }
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
    echo "Done resolving schema.\n";
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage();
}
