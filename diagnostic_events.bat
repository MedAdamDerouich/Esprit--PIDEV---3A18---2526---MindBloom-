@echo off
echo ============================================
echo DIAGNOSTIC MODULE EVENEMENTS - MINDBLOOM
echo ============================================
echo.

cd /d C:\Users\pc\Desktop\Esprit--PIDEV---3A18---2526---MindBloom-

echo [1/5] Verification des fichiers...
echo.
if exist "src\Controller\EventController.php" (
    echo [OK] EventController.php existe
) else (
    echo [ERREUR] EventController.php manquant
)

if exist "src\Controller\EventPatientController.php" (
    echo [OK] EventPatientController.php existe
) else (
    echo [ERREUR] EventPatientController.php manquant
)

if exist "src\Entity\Event.php" (
    echo [OK] Event.php existe
) else (
    echo [ERREUR] Event.php manquant
)

if exist "templates\admin\event\index.html.twig" (
    echo [OK] Template admin index existe
) else (
    echo [ERREUR] Template admin index manquant
)

if exist "templates\patient\event\index.html.twig" (
    echo [OK] Template patient index existe
) else (
    echo [ERREUR] Template patient index manquant
)

if exist "public\css\events.css" (
    echo [OK] CSS events existe
) else (
    echo [ERREUR] CSS events manquant
)

echo.
echo [2/5] Verification du cache...
php bin/console cache:clear --no-interaction
echo [OK] Cache vide

echo.
echo [3/5] Verification des migrations...
php bin/console doctrine:migrations:status

echo.
echo [4/5] Liste des routes evenements...
php bin/console debug:router | findstr event

echo.
echo [5/5] Test de connexion a la base de donnees...
php bin/console doctrine:query:sql "SHOW TABLES" 2>nul
if %ERRORLEVEL% EQU 0 (
    echo [OK] Connexion a la base de donnees reussie
) else (
    echo [ERREUR] Probleme de connexion a la base de donnees
    echo Verifiez les parametres dans .env
)

echo.
echo ============================================
echo DIAGNOSTIC TERMINE
echo ============================================
echo.
echo Si tout est OK ci-dessus, executez:
echo   php bin/console doctrine:migrations:migrate
echo.
pause
