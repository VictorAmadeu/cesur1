# Configuracion movil (Cordova)

Este directorio agrupa la configuracion necesaria para compilar la APK sin afectar el build web.
El build web ignora esta carpeta.

## Proposito de mobile-config/
- Centraliza la configuracion Cordova.
- Permite preparar solo los archivos necesarios antes de compilar.
- Mantiene la rama unificada limpia para web.

## Archivos y carpetas (descripcion)
- `mobile-config/mobile-config.js`
  Configuracion principal de Cordova para Meteor. Define App.info, iconos,
  preferencias Android y configuracion de plugins. Usa CLEAR_HTTP para permitir
  HTTP solo en desarrollo (10.0.2.2).

- `mobile-config/settings-development-mobile.json`
  Settings de desarrollo movil. Por defecto apunta a `http://10.0.2.2:8000`.

- `mobile-config/cordova-plugins`
  Lista de plugins Cordova usados en la APK:
  - cordova-plugin-file
  - cordova-plugin-file-opener2
  - cordova-plugin-geolocation
  - cordova-plugin-meteor-webapp
  - cordova-plugin-nativestorage
  - cordova-plugin-statusbar

- `mobile-config/config.xml`
  Referencia historica. Se conserva para consulta, pero el flujo actual no lo
  copia al root. Meteor genera config.xml desde `mobile-config.js`.

- `mobile-config/assets/icons/android/*.png`
  Iconos del launcher (mdpi, hdpi, xhdpi, xxhdpi, xxxhdpi).

- `mobile-config/resources/android/xml/network_security_config.xml`
  Configuracion de seguridad de red (HTTP permitido solo a 10.0.2.2).

- `mobile-config/cordova-build-override/...`
  Overrides Cordova para Android. Actualmente contiene una copia de
  network_security_config.xml.

- `mobile-config/types/cordova-app.d.ts`
  Tipado minimo para `App` (ayuda al editor y evita falsos errores).

## Preparacion antes de compilar (scripts/cordova-sync.ps1)

El script `scripts/cordova-sync.ps1` copia al root los archivos necesarios y
crea un backup temporal para restaurar el estado web.

### Preparar (copia al root)
```powershell
powershell -ExecutionPolicy Bypass -File scripts\cordova-sync.ps1 -Mode prepare
```

Copia al root:
- `mobile-config.js`
- `resources/android/xml/network_security_config.xml`
- `cordova-build-override/...`
- `assets/icons/android/*.png`
- `mobile-config/cordova-plugins` -> `.meteor/cordova-plugins`

### Restaurar (volver al estado web)
```powershell
powershell -ExecutionPolicy Bypass -File scripts\cordova-sync.ps1 -Mode restore
```

## Compilacion (meteor run android)

### Desarrollo (HTTP permitido a 10.0.2.2)
```powershell
powershell -ExecutionPolicy Bypass -File scripts\cordova-sync.ps1 -Mode prepare
$env:CLEAR_HTTP = "1"
meteor run android --settings mobile-config\settings-development-mobile.json
```

### Produccion (HTTP desactivado)
```powershell
powershell -ExecutionPolicy Bypass -File scripts\cordova-sync.ps1 -Mode prepare
$env:CLEAR_HTTP = "0"
meteor run android --settings settings-production.json
```

## Nota sobre CLEAR_HTTP
- `CLEAR_HTTP=1` solo en desarrollo.
- `CLEAR_HTTP=0` en produccion.

## Nota sobre plugins AndroidX
En `mobile-config.js` se configuran `cordova-plugin-androidx` y
`cordova-plugin-androidx-adapter`, pero no aparecen en `mobile-config/cordova-plugins`.
Si el build falla por plugin ausente, alinea ambos (sin tocar master ni
Develop-Mobile).
