@echo off
echo ========================================
echo VOIR LES DERNIERES ERREURS SYMFONY
echo ========================================
echo.

cd /d C:\Users\pc\Desktop\Esprit--PIDEV---3A18---2526---MindBloom-

if exist "var\log\dev.log" (
    echo Derniere 50 lignes du log:
    echo.
    powershell -Command "Get-Content var\log\dev.log -Tail 50"
) else (
    echo Aucun fichier de log trouve.
)

echo.
pause
