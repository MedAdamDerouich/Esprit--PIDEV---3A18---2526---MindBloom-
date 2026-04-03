@echo off
setlocal enabledelayedexpansion

echo ========================================
echo DIAGNOSTIC COMPLET MODULE EVENEMENTS
echo ========================================
echo.

cd /d C:\Users\pc\Desktop\Esprit--PIDEV---3A18---2526---MindBloom-

echo [TEST 1] Verification du serveur Symfony...
echo.
tasklist /FI "IMAGENAME eq php.exe" 2>NUL | find /I /N "php.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] Serveur PHP en cours d'execution
) else (
    echo [WARNING] Aucun serveur PHP detecte
    echo Lancez: symfony server:start OU php -S localhost:8000 -t public
)

echo.
echo [TEST 2] Verification des fichiers controllers...
echo.
if exist "src\Controller\EventController.php" (
    echo [OK] EventController.php existe
) else (
    echo [ERREUR] EventController.php MANQUANT!
)

if exist "src\Controller\EventPatientController.php" (
    echo [OK] EventPatientController.php existe
) else (
    echo [ERREUR] EventPatientController.php MANQUANT!
)

echo.
echo [TEST 3] Verification des templates...
echo.
if exist "templates\admin\event\index.html.twig" (
    echo [OK] Template admin index existe
) else (
    echo [ERREUR] Template admin\event\index.html.twig MANQUANT!
)

if exist "templates\patient\event\index.html.twig" (
    echo [OK] Template patient index existe
) else (
    echo [ERREUR] Template patient\event\index.html.twig MANQUANT!
)

echo.
echo [TEST 4] Verification du CSS...
echo.
if exist "public\css\events.css" (
    echo [OK] events.css existe
) else (
    echo [WARNING] events.css manquant
)

echo.
echo [TEST 5] Nettoyage cache...
echo.
php bin/console cache:clear --no-interaction
if %ERRORLEVEL% EQU 0 (
    echo [OK] Cache vide
) else (
    echo [ERREUR] Impossible de vider le cache
)

echo.
echo [TEST 6] Liste des routes evenements...
echo.
php bin/console debug:router | findstr /i event
if %ERRORLEVEL% NEQ 0 (
    echo [ERREUR] Aucune route evenement trouvee!
    echo Les controllers ne sont pas charges.
) else (
    echo [OK] Routes evenements detectees
)

echo.
echo [TEST 7] Verification base de donnees...
echo.
php -r "try { $pdo = new PDO('mysql:host=127.0.0.1;dbname=test', 'root', ''); echo '[OK] Connexion MySQL reussie\n'; $stmt = $pdo->query('SHOW TABLES LIKE \"evenement\"'); if($stmt->rowCount() > 0) { echo '[OK] Table evenement existe\n'; $stmt = $pdo->query('SELECT COUNT(*) as c FROM evenement'); $r = $stmt->fetch(); echo '[INFO] ' . $r['c'] . ' evenement(s) dans la base\n'; } else { echo '[ERREUR] Table evenement N\'EXISTE PAS!\n'; } $stmt = $pdo->query('SHOW TABLES LIKE \"participation\"'); if($stmt->rowCount() > 0) { echo '[OK] Table participation existe\n'; } else { echo '[ERREUR] Table participation N\'EXISTE PAS!\n'; } $stmt = $pdo->query('SHOW TABLES LIKE \"users\"'); if($stmt->rowCount() > 0) { echo '[OK] Table users existe\n'; } else { echo '[ERREUR] Table users N\'EXISTE PAS!\n'; } } catch(Exception $e) { echo '[ERREUR] ' . $e->getMessage() . '\n'; }" 2>NUL

echo.
echo [TEST 8] Test acces direct aux routes...
echo.
echo Testez manuellement dans votre navigateur:
echo.
echo Pour ADMIN (necessite role ADMIN):
echo   http://localhost:8000/admin/evenements
echo.
echo Pour PATIENT (necessite role PATIENT):
echo   http://localhost:8000/patient/evenements
echo.
echo Si vous voyez "Access Denied", connectez-vous avec le bon role.
echo Si vous voyez "404 Not Found", les routes ne sont pas chargees.
echo Si vous voyez une erreur 500, regardez var\log\dev.log
echo.

echo ========================================
echo DIAGNOSTIC TERMINE
echo ========================================
echo.
echo Pour voir les erreurs detaillees:
echo   type var\log\dev.log
echo.
pause
