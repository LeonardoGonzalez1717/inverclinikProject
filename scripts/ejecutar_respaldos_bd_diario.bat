@echo off

REM
REM

REM

cd /d "%~dp0.."

"C:\xampp\php\php.exe" "%~dp0respaldos_bd_diario.php"

exit /b %ERRORLEVEL%

