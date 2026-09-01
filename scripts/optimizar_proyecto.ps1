# ==============================================================================
# Script de Optimizacion y Limpieza de Inverclinik
# ==============================================================================

$ErrorActionPreference = "Continue"

$Root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
Write-Host "Iniciando optimizacion en: $Root" -ForegroundColor Cyan

# 1. Eliminar archivos .git internos y metadatos dentro de vendor
Write-Host "`n[1/5] Eliminando repositorios .git y metadatos internos en vendor/..." -ForegroundColor Green
$GitFolders = Get-ChildItem -Path "$Root\vendor" -Directory -Recurse -Force -ErrorAction SilentlyContinue | Where-Object {
    $_.Name -in @('.git', '.github', '.phan')
}
foreach ($item in $GitFolders) {
    Write-Host " - Eliminando carpeta: $($item.FullName.Replace($Root, ''))" -ForegroundColor Yellow
    Remove-Item -Path $item.FullName -Recurse -Force -ErrorAction SilentlyContinue
}

# 2. Eliminar tests, docs y examples dentro de vendor
Write-Host "`n[2/5] Eliminando carpetas de pruebas (tests), ejemplos (examples) y documentacion en vendor/..." -ForegroundColor Green
$TestFolders = Get-ChildItem -Path "$Root\vendor" -Directory -Recurse -Force -ErrorAction SilentlyContinue | Where-Object {
    $_.Name -in @('tests', 'test', 'examples', 'docs', 'tools')
}
foreach ($item in $TestFolders) {
    Write-Host " - Eliminando carpeta: $($item.FullName.Replace($Root, ''))" -ForegroundColor Yellow
    Remove-Item -Path $item.FullName -Recurse -Force -ErrorAction SilentlyContinue
}

# 3. Purgar fuentes asiaticas / pesadas no utilizadas en TCPDF
Write-Host "`n[3/5] Purgando fuentes no latinas en TCPDF..." -ForegroundColor Green
$TcpdfFontsDir = "$Root\vendor\tecnickcom\tcpdf\fonts"
if (Test-Path $TcpdfFontsDir) {
    $HeavyFontPrefixes = @(
        'cid0*', 'ae_*', 'aealarabiya*', 'aefurat*',
        'kozminpro*', 'kozgoprob*', 'msungstdlight*', 'hy*',
        'stsongstdlight*', 'hysmyeongjostdmedium*', 'droidsansfallback*',
        'unbatang*'
    )
    foreach ($pattern in $HeavyFontPrefixes) {
        $files = Get-ChildItem -Path $TcpdfFontsDir -Filter $pattern -File -ErrorAction SilentlyContinue
        foreach ($f in $files) {
            Remove-Item -Path $f.FullName -Force -ErrorAction SilentlyContinue
        }
    }
}

# 4. Limpiar archivos zip duplicados viejos en la raiz y composer.phar
Write-Host "`n[4/5] Limpiando archivos .zip viejos y composer.phar en la raiz..." -ForegroundColor Green
$OldFiles = @(
    "$Root\inverclinik_deploy (2).zip",
    "$Root\composer.phar",
    "$Root\analizar_peso.ps1"
)
foreach ($f in $OldFiles) {
    if (Test-Path $f) {
        Write-Host " - Eliminando: $f" -ForegroundColor Yellow
        Remove-Item -Path $f -Force -ErrorAction SilentlyContinue
    }
}

# 5. Medir el nuevo tamano del proyecto
Write-Host "`n[5/5] Calculando nuevo peso del proyecto..." -ForegroundColor Green

$VendorSize = (Get-ChildItem "$Root\vendor" -Recurse -File -Force -ErrorAction SilentlyContinue | Measure-Object -Property Length -Sum).Sum
$VendorMB = [math]::Round($VendorSize / 1MB, 2)

$TotalSize = (Get-ChildItem $Root -Recurse -File -Force -ErrorAction SilentlyContinue | Where-Object { $_.FullName -notmatch '[\\/]dist[\\/]' } | Measure-Object -Property Length -Sum).Sum
$TotalMB = [math]::Round($TotalSize / 1MB, 2)

Write-Host "`n==================================================" -ForegroundColor Cyan
Write-Host "         OPTIMIZACION FINALIZADA                  " -ForegroundColor Yellow
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host " Nuevo peso de vendor/:     $VendorMB MB (antes: ~131 MB)" -ForegroundColor Green
Write-Host " Peso total del proyecto:   $TotalMB MB" -ForegroundColor Green
Write-Host "==================================================" -ForegroundColor Cyan
