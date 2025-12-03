# Flujo de descargas de documentos: Reglas de negocio que deberán cubrir los tests unitarios.

1. **UI en web**: `imports/ui/components/Documento/DocumentosWeb.jsx` muestra una tabla con pestañas para los distintos tipos de documentos.
2. **UI en móvil**: `imports/ui/components/Documento/DocumentosMobile.jsx` utiliza un selector y tarjetas para optimizar la experiencia en pantallas pequeñas.
3. **Servicio**: `callApi('document')` devuelve una agrupación por tipo; el endpoint `document/mark-read` marca un documento como visto.
4. **Descarga**:
   - **Web**: usa `file-saver` y la función `base64ToBlob` (`imports/utils/files.js`) para convertir el base64 en un Blob y descargarlo con el MIME correcto.
   - **Móvil**: crea un enlace `data:...` y dispara la descarga directa.
5. **Permisos**: el acceso a esta sección se controla mediante el flag `allowDocument` del contexto de permisos.
6. **Errores**: se controlan en la UI; todavía no existen tests unitarios para este flujo. Se recomienda extraer un hook común de descarga y añadir pruebas específicas.
