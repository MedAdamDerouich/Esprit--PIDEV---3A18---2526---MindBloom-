@echo off
REM Créer les répertoires
mkdir templates\admin\event
mkdir templates\patient\event

REM Vérifier la création
echo.
echo === Répertoires créés avec succès ===
echo.
dir templates /s /b
