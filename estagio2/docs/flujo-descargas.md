# Flujo de descargas de documentos

> Objetivo: documentar cómo Intranek lista, marca y descarga documentos en web y en móvil, y qué debe cubrir la batería de tests unitarios.

---

## 1. Entrada y validaciones

**UI en web**

- Componente principal: `imports/ui/components/Documento/DocumentosWeb.jsx`.
- Muestra una tabla con pestañas para los diferentes tipos de documento.

**UI en móvil**

- Componente principal: `imports/ui/components/Documento/DocumentosMobile.jsx`.
- Emplea un selector y tarjetas para optimizar la experiencia en pantallas pequeñas.

**Validaciones básicas**

- Sólo se muestran documentos para los que el usuario tiene permiso (`allowDocument`).
- Se comprueba que existan datos antes de pintar tablas o tarjetas.

---

## 2. Servicios y efectos secundarios

**Servicio de documentos**

- `callApi('document')`:
  - Devuelve una agrupación de documentos por tipo.
- Endpoint `document/mark-read`:
  - Marca un documento como visto.

**Descarga de documentos**

- Versión web:
  - Usa la librería `file-saver`.
  - Utiliza la función `base64ToBlob` (`imports/utils/files.js`) para convertir el base64 a `Blob`.
  - Descarga el archivo con el MIME correcto.

- Versión móvil:
  - Crea un enlace `data:...` con el base64.
  - Dispara la descarga directa adaptada a móviles.

**Permisos**

- El acceso a esta sección se controla mediante el flag `allowDocument` que proviene del contexto de permisos.

---

## 3. Errores gestionados

- Errores de carga de documentos:
  - Se gestionan en la UI (mensajes de error en pantalla).
- Errores en `document/mark-read`:
  - Se informa al usuario si no se puede marcar un documento como leído.

---

## 4. Cobertura prevista con tests unitarios

Actualmente no existen tests unitarios específicos para este flujo. Se recomienda:

1. Extraer un hook común de descarga (web/móvil) que envuelva:
   - La conversión `base64 → Blob`.
   - La descarga efectiva del archivo.
2. Añadir pruebas que verifiquen:
   - Que `callApi('document')` se llama con los parámetros esperados.
   - Que se respeta el flag `allowDocument` antes de mostrar o descargar documentos.
   - Que los errores de red se reflejan correctamente en la UI.
