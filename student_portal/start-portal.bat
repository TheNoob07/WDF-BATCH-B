@echo off
setlocal
set "PHP_EXE=C:\xampp\php\php.exe"
set "PROJECT_DIR=%~dp0"

if not exist "%PHP_EXE%" (
    echo Could not find XAMPP PHP at "%PHP_EXE%".
    echo Install XAMPP or update PHP_EXE in this file.
    pause
    exit /b 1
)

echo Starting Student Portal at http://127.0.0.1:8000/
echo Keep this window open while using the portal.
echo Start MySQL from the XAMPP Control Panel in a separate step.
echo Press Ctrl+C here to stop the portal server.
"%PHP_EXE%" -S 127.0.0.1:8000 -t "%PROJECT_DIR%" "%PROJECT_DIR%php\router.php"
endlocal
