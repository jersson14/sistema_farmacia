<?php
session_start();
if (!isset($_SESSION['idusuario'])) {
    http_response_code(403);
    exit('Acceso denegado. <a href="../index.php">Iniciar sesión</a>');
}

$certFile = __DIR__ . '/../public/qz_cert.pem';
if (!file_exists($certFile)) {
    exit('Falta el certificado. <a href="setup_qz.php">Ejecuta setup_qz.php primero</a>');
}

$certPem = trim(file_get_contents($certFile));

// Parte 1: todo hasta abrir el here-string del cert (nowdoc — sin interpolación PHP)
$part1 = <<<'ENDPS'
# FarmaciaPOS - Configurador QZ Tray
# Clic derecho -> "Ejecutar con PowerShell"

if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Start-Process powershell.exe "-ExecutionPolicy Bypass -File `"$PSCommandPath`"" -Verb RunAs
    exit
}

$host.UI.RawUI.WindowTitle = "FarmaciaPOS - Configuracion QZ Tray"
$ErrorActionPreference = "Continue"

Write-Host ""
Write-Host "  ================================================" -ForegroundColor Cyan
Write-Host "  FarmaciaPOS - Configuracion de impresion"        -ForegroundColor Cyan
Write-Host "  ================================================" -ForegroundColor Cyan
Write-Host ""

$certContenido = @'
ENDPS;

// Parte 2: cierre del here-string y resto del script
$part2 = <<<'ENDPS'
'@

# -- [1/5] Verificar / instalar QZ Tray --
Write-Host "  [1/5] Verificando QZ Tray..." -ForegroundColor Yellow

$qzPaths = @(
    "$env:LOCALAPPDATA\Programs\qz-tray\qz-tray.exe",
    "$env:LOCALAPPDATA\qz-tray\qz-tray.exe",
    "C:\Program Files\qz-tray\qz-tray.exe",
    "C:\Program Files (x86)\qz-tray\qz-tray.exe",
    "C:\Program Files\QZ Tray\qz-tray.exe",
    "C:\Program Files (x86)\QZ Tray\qz-tray.exe"
)
$qzExe = $qzPaths | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $qzExe) {
    Write-Host "  QZ Tray no instalado. Descargando..." -ForegroundColor Yellow
    $tmpInstaller = "$env:TEMP\qz-tray-setup.exe"
    try {
        $dlUrl = "https://github.com/qzind/tray/releases/download/v2.2.4/qz-tray-2.2.4.exe"
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -Uri $dlUrl -OutFile $tmpInstaller -UseBasicParsing
        Write-Host "  Instalando (modo silencioso)..." -ForegroundColor Yellow
        Start-Process $tmpInstaller -ArgumentList "--mode unattended" -Wait
        Remove-Item $tmpInstaller -Force -ErrorAction SilentlyContinue
        $qzExe = $qzPaths | Where-Object { Test-Path $_ } | Select-Object -First 1
    } catch {
        Write-Host ""
        Write-Host "  ERROR: No se pudo descargar QZ Tray automaticamente." -ForegroundColor Red
        Write-Host "  Descargalo manualmente desde: https://qz.io/download" -ForegroundColor Yellow
        Write-Host "  Instala QZ Tray y vuelve a ejecutar este script." -ForegroundColor Yellow
        Write-Host ""
        Read-Host "  Presiona Enter para salir"
        exit 1
    }
}

if (-not $qzExe) {
    Write-Host "  ERROR: No se encontro qz-tray.exe tras la instalacion." -ForegroundColor Red
    Read-Host "  Presiona Enter para salir"
    exit 1
}
Write-Host "  [OK] QZ Tray encontrado: $qzExe" -ForegroundColor Green

# -- [2/5] Iniciar QZ Tray --
Write-Host "  [2/5] Iniciando QZ Tray..." -ForegroundColor Yellow
$qzRunning = Get-Process "qz-tray" -ErrorAction SilentlyContinue
if (-not $qzRunning) {
    Start-Process $qzExe
    Write-Host "  Esperando que QZ Tray arranque..." -ForegroundColor Yellow
    Start-Sleep -Seconds 7
}
Write-Host "  [OK] QZ Tray en ejecucion" -ForegroundColor Green

# -- [3/5] Buscar root-ca.pem --
Write-Host "  [3/5] Buscando certificado raiz de QZ Tray..." -ForegroundColor Yellow
$rootCaPaths = @(
    "$env:APPDATA\qz\certs\root-ca.pem",
    "$env:APPDATA\qz-tray\certs\root-ca.pem"
)
$rootCa = $null
for ($i = 0; $i -lt 15; $i++) {
    $rootCa = $rootCaPaths | Where-Object { Test-Path $_ } | Select-Object -First 1
    if ($rootCa) { break }
    Start-Sleep -Seconds 2
}

if (-not $rootCa) {
    Write-Host "  AVISO: No se encontro root-ca.pem." -ForegroundColor Yellow
    Write-Host "  Cierra QZ Tray (clic derecho -> Exit), vuelve a abrirlo y ejecuta el script de nuevo." -ForegroundColor Yellow
} else {
    # -- [4/5] Instalar root-ca en Windows --
    Write-Host "  [4/5] Instalando certificado raiz en Windows..." -ForegroundColor Yellow
    try {
        Import-Certificate -FilePath $rootCa -CertStoreLocation Cert:\LocalMachine\Root | Out-Null
        Write-Host "  [OK] Certificado raiz instalado (navegador confiara en QZ Tray)" -ForegroundColor Green
    } catch {
        Write-Host "  AVISO: $($_.Exception.Message)" -ForegroundColor Yellow
    }
}

# -- [5/5] Instalar cert de la app en carpeta auth de QZ Tray --
Write-Host "  [5/5] Instalando certificado de FarmaciaPOS..." -ForegroundColor Yellow
$authDir = "$env:APPDATA\qz\auth"
if (-not (Test-Path $authDir)) {
    New-Item -ItemType Directory -Path $authDir -Force | Out-Null
}
$certContenido | Out-File -FilePath "$authDir\farmacia_pos.pem" -Encoding ASCII -Force
Write-Host "  [OK] Certificado instalado en: $authDir\farmacia_pos.pem" -ForegroundColor Green

# -- Reiniciar QZ Tray para aplicar cambios --
Write-Host ""
Write-Host "  Reiniciando QZ Tray para aplicar cambios..." -ForegroundColor Yellow
Stop-Process -Name "qz-tray" -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2
Start-Process $qzExe

Write-Host ""
Write-Host "  ================================================" -ForegroundColor Green
Write-Host "  Listo! Impresion y gaveta configuradas."          -ForegroundColor Green
Write-Host "  ================================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Siguiente paso: abre el sistema en el navegador" -ForegroundColor White
Write-Host "  y prueba imprimir un ticket." -ForegroundColor White
Write-Host ""
Start-Sleep -Seconds 4
ENDPS;

$script = $part1 . "\r\n" . $certPem . "\r\n" . $part2;

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="instalar_qztray_farmacia.ps1"');
header('Cache-Control: no-cache, no-store');
header('Content-Length: ' . strlen($script));
echo $script;
