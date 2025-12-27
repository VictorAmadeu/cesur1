// @ts-nocheck
/* eslint-disable no-undef */

/**
 * Este archivo NO es JSON, es JavaScript.
 * Por eso podemos usar comentarios (// y /* ... * /) sin problema.
 * Meteor lo lee en tiempo de build para generar la config de Cordova.
 */

// 1) Información básica de la aplicación
App.info({
  id: 'com.ekium.intranek',
  name: 'Intranek',
  version: '1.0.1', // Incrementar SOLO cuando se quiera forzar actualización de la app/icono en dispositivos
  description: 'Intranet Ekium',
  author: 'Ekium',
});

// 2) Iconos del launcher Android
//    Rutas relativas a la carpeta raíz del proyecto Meteor.
App.icons({
  android_mdpi: 'assets/icons/android/mdpi.png',      // 48x48
  android_hdpi: 'assets/icons/android/hdpi.png',      // 72x72
  android_xhdpi: 'assets/icons/android/xhdpi.png',    // 96x96
  android_xxhdpi: 'assets/icons/android/xxhdpi.png',  // 144x144
  android_xxxhdpi: 'assets/icons/android/xxxhdpi.png' // 192x192
});

// 3) Preferencias generales de Android
//    Se convierten en <preference ...> dentro del config.xml de Cordova.
App.setPreference('ShowSplashScreen', 'false');      // No mostrar splash por defecto
App.setPreference('AndroidLaunchMode', 'singleTop'); // Evita instancias duplicadas de la Activity principal

// 3.1) Preferencias específicas para manejo de ficheros en Android
//      - AndroidPersistentFileLocation: recomendado para apps que ya existían
//        antes de actualizar el plugin cordova-plugin-file, así no se pierden
//        rutas antiguas al actualizar. :contentReference[oaicite:1]{index=1}
App.setPreference('AndroidPersistentFileLocation', 'Compatibility');

// 4) Configuración de plugins Cordova usados para descargar/abrir ficheros PDF
//    IMPORTANTE: Los plugins ya han sido añadidos con `meteor add cordova:<plugin>`.
//    Aquí solo afinamos su configuración para el build de Cordova.

/**
 * cordova-plugin-file
 * -------------------
 * Proporciona acceso al sistema de ficheros (lectura/escritura).
 * No necesita opciones especiales para este caso, pero lo declaramos
 * explícitamente para que Meteor lo incluya correctamente.
 */
App.configurePlugin('cordova-plugin-file');

/**
 * cordova-plugin-file-opener2
 * ---------------------------
 * Permite abrir PDFs (y otros tipos de ficheros) con las apps nativas del
 * dispositivo (visor de PDF, etc.).
 * La opción SUPPORT_64BIT es recomendación habitual para builds modernos
 * de Android (arquitecturas 64 bits).
 */
App.configurePlugin('cordova-plugin-file-opener2', {
  SUPPORT_64BIT: 'true'
});

/**
 * cordova-plugin-androidx y cordova-plugin-androidx-adapter
 * ---------------------------------------------------------
 * Aseguran compatibilidad con AndroidX cuando otros plugins usan librerías
 * antiguas de soporte.
 */
App.configurePlugin('cordova-plugin-androidx');
App.configurePlugin('cordova-plugin-androidx-adapter');

// 5) HTTP claro solo en desarrollo (controlado por variable de entorno CLEAR_HTTP)
//    Esto NO se toca: ya está limitado a entorno de desarrollo.
//    Permite que el emulador (10.0.2.2) hable por HTTP con tu backend local.
if (process.env.CLEAR_HTTP === '1') {
  App.appendToConfig(`
    <platform name="android">
      <config-file parent="/*" target="app/src/main/res/xml/network_security_config.xml">
        <network-security-config>
          <domain-config cleartextTrafficPermitted="true">
            <domain includeSubdomains="false">10.0.2.2</domain>
          </domain-config>
        </network-security-config>
      </config-file>
      <edit-config file="app/src/main/AndroidManifest.xml"
                   target="/manifest/application" mode="merge">
        <application android:usesCleartextTraffic="true"
                     android:networkSecurityConfig="@xml/network_security_config" />
      </edit-config>
    </platform>
  `);
}
