@echo off
REM Create the required directories
cd /d "c:\Users\pc\Desktop\Esprit--PIDEV---3A18---2526---MindBloom-"

REM Create templates\admin\event
mkdir "templates\admin\event" 2>nul
if exist "templates\admin\event" (
    echo [OK] Created: templates\admin\event
) else (
    echo [FAILED] Could not create templates\admin\event
)

REM Create templates\event  
mkdir "templates\event" 2>nul
if exist "templates\event" (
    echo [OK] Created: templates\event
) else (
    echo [FAILED] Could not create templates\event
)

REM Verify both directories exist
echo.
echo Verification:
if exist "templates\admin\event" (
    echo [VERIFIED] templates\admin\event exists
) else (
    echo [NOT FOUND] templates\admin\event does not exist
)

if exist "templates\event" (
    echo [VERIFIED] templates\event exists
) else (
    echo [NOT FOUND] templates\event does not exist
)

REM Show directory structure
echo.
echo Directory structure:
dir /s templates | findstr /C:"admin" /C:"event" /C:"templates"
