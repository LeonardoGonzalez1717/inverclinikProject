# ==============================================================================
# Script de Empaquetado Limpio para Despliegue en Produccion
# Proyecto: Inverclinik
# ==============================================================================

$ErrorActionPreference = "Stop"

$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$OutputDir = Join-Path $ProjectRoot "dist"

if (-not (Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
}

$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$ZipName = "inverclinik_deploy_$Timestamp.zip"
$ZipPath = Join-Path $OutputDir $ZipName
$LatestZipPath = Join-Path $ProjectRoot "inverclinik_deploy.zip"

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "   EMPAQUETADOR DE DESPLIEGUE - INVERCLINIK      " -ForegroundColor Yellow
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "Directorio origen: $ProjectRoot" -ForegroundColor Gray
Write-Host "Archivo destino:   $ZipPath" -ForegroundColor Gray
Write-Host ""

# Lista de carpetas a excluir completamente
$ExcludedDirs = @(
    ".git",
    ".github",
    ".vscode",
    ".idea",
    ".gemini",
    ".agents",
    "dist",
    "deploy",
    "node_modules"
)

# Lista de extensiones y patrones de archivo a excluir
$ExcludedFilePatterns = @(
    "*.zip",
    "*.rar",
    "*.7z",
    "*.tar.gz",
    "*.sql",
    "composer.phar",
    "*.log",
    "*.tmp",
    "*.bak",
    "Thumbs.db",
    ".DS_Store"
)

# Recolectar archivos validos
Write-Host "[1/3] Analizando archivos del proyecto..." -ForegroundColor Green

$AllFiles = Get-ChildItem -Path $ProjectRoot -Recurse -File -Force

$FilesToZip = New-Object System.Collections.Generic.List[System.IO.FileInfo]
$TotalRawSize = 0

foreach ($File in $AllFiles) {
    $RelativePath = $File.FullName.Substring($ProjectRoot.Length + 1)
    
    # Comprobar si pertenece a una carpeta excluida
    $SkipDir = $false
    foreach ($Dir in $ExcludedDirs) {
        if ($RelativePath -match "(^|[\\/])" + [regex]::Escape($Dir) + "([\\/]|$)") {
            $SkipDir = $true
            break
        }
    }
    if ($SkipDir) { continue }

    # Comprobar patrones de archivos excluidos
    $SkipFile = $false
    foreach ($Pattern in $ExcludedFilePatterns) {
        if ($File.Name -like $Pattern) {
            $SkipFile = $true
            break
        }
    }
    if ($SkipFile) { continue }

    # Excluir comprobantes locales de prueba
    if ($RelativePath -match "^uploads[\\/]comprobantes_cotizaciones[\\/].+" -and $File.Name -ne ".htaccess") {
        continue
    }

    # Excluir respaldos de BD dentro de storage
    if ($RelativePath -match "^storage[\\/]respaldos_bd[\\/].+\.sql$") {
        continue
    }

    $FilesToZip.Add($File)
    $TotalRawSize += $File.Length
}

$FileCount = $FilesToZip.Count
$RawSizeMB = [math]::Round($TotalRawSize / 1MB, 2)

Write-Host " -> Se encontraron $FileCount archivos para produccion ($RawSizeMB MB sin comprimir)." -ForegroundColor Green
Write-Host ""

# Crear archivo ZIP usando .NET ZipArchive
Write-Host "[2/3] Comprimiendo archivos en ZIP..." -ForegroundColor Green

if (Test-Path $ZipPath) {
    Remove-Item $ZipPath -Force
}

Add-Type -AssemblyName "System.IO.Compression"
Add-Type -AssemblyName "System.IO.Compression.FileSystem"
$ZipArchive = [System.IO.Compression.ZipFile]::Open($ZipPath, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    $Counter = 0
    foreach ($File in $FilesToZip) {
        $Counter++
        $RelativePath = $File.FullName.Substring($ProjectRoot.Length + 1).Replace("\", "/")
        
        if ($Counter % 50 -eq 0 -or $Counter -eq $FileCount) {
            $Percent = [math]::Round(($Counter / $FileCount) * 100)
            Write-Progress -Activity "Comprimiendo proyecto" -Status "$Percent% completado ($Counter de $FileCount)" -PercentComplete $Percent
        }

        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $ZipArchive,
            $File.FullName,
            $RelativePath,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
}
finally {
    $ZipArchive.Dispose()
    Write-Progress -Activity "Comprimiendo proyecto" -Completed
}

# Crear copia como inverclinik_deploy.zip en la raiz
Copy-Item -Path $ZipPath -Destination $LatestZipPath -Force

$ZipSize = (Get-Item $ZipPath).Length
$ZipSizeMB = [math]::Round($ZipSize / 1MB, 2)

Write-Host ""
Write-Host "[3/3] Empaquetado completado con exito!" -ForegroundColor Cyan
Write-Host "--------------------------------------------------" -ForegroundColor DarkGray
Write-Host " Archivos procesados:   $FileCount" -ForegroundColor White
Write-Host " Tamano original:       $RawSizeMB MB" -ForegroundColor White
Write-Host " Tamano final ZIP:      $ZipSizeMB MB" -ForegroundColor Yellow
Write-Host " Archivo con fecha:     $ZipPath" -ForegroundColor White
Write-Host " Archivo en raiz:       $LatestZipPath" -ForegroundColor Green
Write-Host "--------------------------------------------------" -ForegroundColor DarkGray
Write-Host "Listo para subir directamente a cPanel / Hosting." -ForegroundColor Green
