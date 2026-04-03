@echo off
echo Vidage du cache...
cd /d C:\Users\pc\Desktop\Esprit--PIDEV---3A18---2526---MindBloom-
php bin/console cache:clear
echo.
echo ======================================
echo CORRECTION TERMINEE !
echo ======================================
echo.
echo Le champ qr_code a ete desactive.
echo Testez maintenant:
echo.
echo PATIENT: http://localhost:8000/patient/evenements
echo ADMIN:   http://localhost:8000/admin/evenements
echo.
pause
