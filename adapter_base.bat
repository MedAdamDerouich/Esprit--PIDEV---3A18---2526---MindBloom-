@echo off
echo ============================================
echo ADAPTATION BASE DE DONNEES EXISTANTE
echo ============================================
echo.
echo Ce script va adapter votre base existante
echo pour la rendre compatible avec Symfony.
echo.
echo Modifications apportees:
echo - Renommer id_utilisateur en id_user
echo - Ajouter le champ qr_code
echo - Adapter les types de colonnes
echo - Marquer les migrations comme executees
echo.
pause

cd /d C:\Users\pc\Desktop\Esprit--PIDEV---3A18---2526---MindBloom-

echo.
echo [Etape 1/2] Adaptation de la base...
php adapter_base_existante.php
echo.

if %ERRORLEVEL% EQU 0 (
    echo [Etape 2/2] Vidage du cache Symfony...
    php bin/console cache:clear --no-interaction
    echo.
    
    echo ============================================
    echo ADAPTATION TERMINEE AVEC SUCCES!
    echo ============================================
    echo.
    echo Vous pouvez maintenant acceder aux evenements:
    echo.
    echo   ADMIN:   http://localhost:8000/admin/evenements
    echo   PATIENT: http://localhost:8000/patient/evenements
    echo.
) else (
    echo.
    echo [ERREUR] L'adaptation a echoue!
    echo Verifiez les messages ci-dessus.
    echo.
)

pause
