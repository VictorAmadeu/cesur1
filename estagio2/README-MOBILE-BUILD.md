# Generar APK (resumen rapido)

Este documento resume como compilar la APK en desarrollo y produccion.

## Requisitos
- Android SDK
- JDK 11+
- Gradle

## Desarrollo (emulador)
Usa el script npm que ya prepara Cordova y permite HTTP:
```powershell
npm run start:mobile
```

Equivalente manual:
```powershell
powershell -ExecutionPolicy Bypass -File scripts\cordova-sync.ps1 -Mode prepare
$env:CLEAR_HTTP = "1"
meteor run android --settings mobile-config\settings-development-mobile.json
```

## Produccion
Usa el script npm que ya prepara Cordova y desactiva HTTP:
```powershell
npm run build:android
```

Equivalente manual:
```powershell
powershell -ExecutionPolicy Bypass -File scripts\cordova-sync.ps1 -Mode prepare
$env:CLEAR_HTTP = "0"
meteor run android --settings settings-production.json
```

## Recordatorio sobre CLEAR_HTTP
- `CLEAR_HTTP=1` solo para desarrollo.
- `CLEAR_HTTP=0` en produccion.

## Restaurar estado web (opcional)
```powershell
powershell -ExecutionPolicy Bypass -File scripts\cordova-sync.ps1 -Mode restore
```
