@echo off
echo ============================================
echo INSTALLATION FORCEE MODULE EVENEMENTS
echo ============================================
echo.
echo Cette methode contourne la migration problematique
echo et cree directement les tables evenement et participation.
echo.
pause

cd /d C:\Users\pc\Desktop\Esprit--PIDEV---3A18---2526---MindBloom-

echo.
echo [Etape 1/3] Creation des tables via script PHP...
php install_events_force.php
echo.

if %ERRORLEVEL% EQU 0 (
    echo [Etape 2/3] Vidage du cache...
    php bin/console cache:clear --no-interaction
    echo.
    
    echo [Etape 3/3] Verification des routes...
    php bin/console debug:router | findstr event
    echo.
    
    echo ============================================
    echo INSTALLATION TERMINEE AVEC SUCCES!
    echo ============================================
    echo.
) else (
    echo [ERREUR] L'installation a echoue!
    echo.
)

pause
