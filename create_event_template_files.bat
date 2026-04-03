@echo off
REM Create directories
mkdir templates\admin\event 2>nul
mkdir templates\event 2>nul

REM Create template files with placeholder content
echo {# Placeholder #} > templates\admin\event\index.html.twig
echo {# Placeholder #} > templates\admin\event\new.html.twig
echo {# Placeholder #} > templates\admin\event\edit.html.twig
echo {# Placeholder #} > templates\admin\event\show.html.twig
echo {# Placeholder #} > templates\admin\event\participants.html.twig
echo {# Placeholder #} > templates\event\index.html.twig
echo {# Placeholder #} > templates\event\show.html.twig
echo {# Placeholder #} > templates\event\my_participations.html.twig

echo.
echo === All directories and template files created successfully ===
echo.
echo Created directories:
echo   - templates\admin\event\
echo   - templates\event\
echo.
echo Created template files:
echo   - templates\admin\event\index.html.twig
echo   - templates\admin\event\new.html.twig
echo   - templates\admin\event\edit.html.twig
echo   - templates\admin\event\show.html.twig
echo   - templates\admin\event\participants.html.twig
echo   - templates\event\index.html.twig
echo   - templates\event\show.html.twig
echo   - templates\event\my_participations.html.twig
