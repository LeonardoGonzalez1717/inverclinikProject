@echo off
REM Ejecutar desde el Programador de tareas (2 disparos: 08:30 y 13:30, hora Venezuela).
REM Ajusta la ruta de PHP si tu XAMPP esta en otra unidad o carpeta.
cd /d "%~dp0.."
"C:\xampp\php\php.exe" "%~dp0actualizar_tasa_programada.php"
