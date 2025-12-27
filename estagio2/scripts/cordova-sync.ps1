<#
cordova-sync.ps1
Sincroniza archivos Cordova desde mobile-config/ hacia el root (prepare) y revierte cambios (restore).

Uso:
  powershell -ExecutionPolicy Bypass -File scripts\cordova-sync.ps1 -Mode prepare
  powershell -ExecutionPolicy Bypass -File scripts\cordova-sync.ps1 -Mode restore
#>

[CmdletBinding()]
param(
  [Parameter(Mandatory = $true)]
  [ValidateSet("prepare", "restore")]
  [string]$Mode
)

$ErrorActionPreference = "Stop"

function Ensure-Directory([string]$Path) {
  if (-not (Test-Path -LiteralPath $Path)) {
    New-Item -ItemType Directory -Path $Path -Force | Out-Null
  }
}

function Copy-FileSafe([string]$Source, [string]$Destination) {
  if (-not (Test-Path -LiteralPath $Source)) {
    throw "No existe el archivo origen: $Source"
  }
  Ensure-Directory (Split-Path -Path $Destination -Parent)
  Copy-Item -LiteralPath $Source -Destination $Destination -Force
}

function Backup-Path([string]$PathToBackup, [string]$BackupPath) {
  if (Test-Path -LiteralPath $PathToBackup) {
    Ensure-Directory (Split-Path -Path $BackupPath -Parent)

    $item = Get-Item -LiteralPath $PathToBackup
    if ($item.PSIsContainer) {
      Copy-Item -LiteralPath $PathToBackup -Destination $BackupPath -Recurse -Force
    } else {
      Copy-Item -LiteralPath $PathToBackup -Destination $BackupPath -Force
    }
    return $true
  }
  return $false
}

# Root del proyecto (carpeta padre de /scripts)
$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$MobileRoot  = Join-Path $ProjectRoot "mobile-config"

# Backup temporal (se borra en restore)
$BackupRoot    = Join-Path $ProjectRoot ".cordova-sync-backup"
$ManifestPath  = Join-Path $BackupRoot "manifest.json"

# Archivos a copiar (mÃ­nimo y seguro, solo lo necesario)
$Copies = @(
  @{ src = (Join-Path $MobileRoot "mobile-config.js"); dst = (Join-Path $ProjectRoot "mobile-config.js") },

  @{ src = (Join-Path $MobileRoot "resources/android/xml/network_security_config.xml");
     dst = (Join-Path $ProjectRoot "resources/android/xml/network_security_config.xml") },

  @{ src = (Join-Path $MobileRoot "cordova-build-override/platforms/android/app/src/main/res/xml/network_security_config.xml");
     dst = (Join-Path $ProjectRoot "cordova-build-override/platforms/android/app/src/main/res/xml/network_security_config.xml") },

  @{ src = (Join-Path $MobileRoot "cordova-build-override/resources/android/xml/network_security_config.xml");
     dst = (Join-Path $ProjectRoot "cordova-build-override/resources/android/xml/network_security_config.xml") },

  # Plugins: mobile-config/cordova-plugins -> .meteor/cordova-plugins
  @{ src = (Join-Path $MobileRoot "cordova-plugins");
     dst = (Join-Path $ProjectRoot ".meteor/cordova-plugins");
     special = "plugins" }
)

# Iconos android (5 tamaÃ±os)
$IconSizes = @("mdpi","hdpi","xhdpi","xxhdpi","xxxhdpi")
foreach ($size in $IconSizes) {
  $Copies += @{
    src = (Join-Path $MobileRoot "assets/icons/android/$size.png")
    dst = (Join-Path $ProjectRoot "assets/icons/android/$size.png")
  }
}

if ($Mode -eq "prepare") {
  if (-not (Test-Path -LiteralPath $MobileRoot)) {
    throw "No existe '$MobileRoot'. AsegÃºrate de haber completado los pasos 3/4 (crear/copiar mobile-config/)."
  }

  Ensure-Directory $BackupRoot

  if (Test-Path -LiteralPath $ManifestPath) {
    throw "Ya existe '$ManifestPath'. Parece que 'prepare' ya se ejecutÃ³. Ejecuta 'restore' antes de volver a preparar."
  }

  # Backup â€œwebâ€ de cordova-plugins si existe
  $PluginsDst = Join-Path $ProjectRoot ".meteor/cordova-plugins"
  $PluginsWeb = Join-Path $ProjectRoot ".meteor/cordova-plugins.web"
  Ensure-Directory (Split-Path -Path $PluginsDst -Parent)
  if ((Test-Path -LiteralPath $PluginsDst) -and (-not (Test-Path -LiteralPath $PluginsWeb))) {
    Copy-Item -LiteralPath $PluginsDst -Destination $PluginsWeb -Force
  }

  $manifest = [ordered]@{
    createdAt = (Get-Date).ToString("o")
    targets   = @()
  }

  foreach ($c in $Copies) {
    $dst = $c.dst

    # Ruta relativa para guardar backup espejo dentro de .cordova-sync-backup/
    $rel = $dst.Substring($ProjectRoot.Length).TrimStart('\','/')
    $backupPath = Join-Path $BackupRoot $rel

    $existed = Test-Path -LiteralPath $dst
    $null = $manifest.targets += [ordered]@{ path = $rel; existed = $existed }

    if ($existed) {
      Backup-Path -PathToBackup $dst -BackupPath $backupPath | Out-Null
    }

    Copy-FileSafe -Source $c.src -Destination $dst
  }

  ($manifest | ConvertTo-Json -Depth 6) | Out-File -LiteralPath $ManifestPath -Encoding UTF8

  Write-Host "âœ… PREPARE OK. Archivos Cordova sincronizados en el root." -ForegroundColor Green
  Write-Host "âš ï¸ Importante: NO comitees estos archivos del root. Cuando termines el build mÃ³vil, ejecuta RESTORE." -ForegroundColor Yellow
  exit 0
}

if ($Mode -eq "restore") {
  # Si hay manifest, revertimos todo exactamente.
  if (Test-Path -LiteralPath $ManifestPath) {
    $manifest = Get-Content -LiteralPath $ManifestPath -Raw | ConvertFrom-Json

    foreach ($t in $manifest.targets) {
      $dst = Join-Path $ProjectRoot $t.path
      $backupPath = Join-Path $BackupRoot $t.path

      if ($t.existed -eq $true) {
        if (Test-Path -LiteralPath $backupPath) {
          # Restaurar backup (archivo o carpeta)
          if ((Get-Item -LiteralPath $backupPath).PSIsContainer) {
            # Reemplazo limpio de carpeta
            if (Test-Path -LiteralPath $dst) { Remove-Item -LiteralPath $dst -Recurse -Force }
            Ensure-Directory (Split-Path -Path $dst -Parent)
            Copy-Item -LiteralPath $backupPath -Destination $dst -Recurse -Force
          } else {
            Copy-Item -LiteralPath $backupPath -Destination $dst -Force
          }
        }
      } else {
        # No existÃ­a antes => borrar lo que se copiÃ³
        if (Test-Path -LiteralPath $dst) {
          Remove-Item -LiteralPath $dst -Recurse -Force
        }
      }
    }

    # Limpieza del backup temporal
    Remove-Item -LiteralPath $BackupRoot -Recurse -Force
  } else {
    # Restore â€œmÃ­nimoâ€ si no hay manifest (por seguridad)
    $RootMobileConfig = Join-Path $ProjectRoot "mobile-config.js"
    if (Test-Path -LiteralPath $RootMobileConfig) {
      Remove-Item -LiteralPath $RootMobileConfig -Force
    }

    $PluginsDst = Join-Path $ProjectRoot ".meteor/cordova-plugins"
    $PluginsWeb = Join-Path $ProjectRoot ".meteor/cordova-plugins.web"
    if (Test-Path -LiteralPath $PluginsWeb) {
      Ensure-Directory (Split-Path -Path $PluginsDst -Parent)
      Copy-Item -LiteralPath $PluginsWeb -Destination $PluginsDst -Force
    }
  }

  Write-Host "âœ… RESTORE OK. ConfiguraciÃ³n web restaurada." -ForegroundColor Green
  exit 0
}
