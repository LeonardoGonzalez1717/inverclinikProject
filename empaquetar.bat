@echo off
chcp 65001 >nul
title Empaquetador de Despliegue - Inverclinik
echo ==================================================
echo    EMPAQUETANDO PROYECTO PARA PRODUCCIÓN...
echo ==================================================
echo.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\empaquetar_deploy.ps1"

echo.
echo Presiona cualquier tecla para salir...
pause >nul
