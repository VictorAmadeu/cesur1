# Flujo de ausencias/licencias

> Objetivo: documentar cómo se gestionan las ausencias/licencias desde la UI y los servicios de Intranek, y qué reglas de negocio deberán comprobar los tests unitarios.

---

## 1. Entrada y validaciones

**Punto de entrada (UI)**

- Componentes de interfaz:
  - `imports/ui/components/Ausencia/*`
  - Página `imports/ui/pages/Ausencias.jsx`.

**Validaciones en la UI**

- Altas y ediciones de licencias:
  - Validación de fechas (inicio / fin coherentes).
  - Campos obligatorios (tipo de licencia, comentarios, etc.).
  - Gestión de adjuntos (archivos en base64) cuando sean necesarios.

---

## 2. Servicios y efectos secundarios

**Servicio principal: `LicenseService`**

Métodos expuestos:

- `get` – obtiene licencias por año.
- `getOne` – trae el detalle de una licencia y sus documentos.
- `register` – crea nuevas licencias, con o sin adjuntos (archivos enviados en base64).
- `edit` – modifica licencias existentes (fechas, comentarios, adjuntos).
- `deleteDocument` – elimina adjuntos específicos.
- `pendingSummary` y `pendingList` – exponen licencias pendientes de aprobación para supervisores.

**Permisos y roles**

- Los permisos se leen desde:
  - `PermissionsContext`.
  - Cookies (`role`, `permissions`).
- Estos permisos condicionan quién puede:
  - Crear licencias.
  - Editar o aprobar licencias.
  - Ver listados de pendientes.

**Descarga / visualización de adjuntos**

- Basada en los datos devueltos por `getOne`.
- La UI específica decide:
  - Si se abre el documento en una nueva pestaña.
  - Si se descarga directamente.
- Se recomienda extraer la lógica de descarga a un hook reutilizable para evitar duplicar código.

---

## 3. Errores gestionados

- `licenseService`:
  - Propaga excepciones cuando la API falla.
  - Devuelve `response.data` en los casos correctos.
- La UI:
  - Muestra mensajes de error cuando una licencia no puede crearse, editarse o cargarse.
  - Permite reintentar la operación sin perder totalmente el estado del formulario.

---

## 4. Cobertura prevista con tests unitarios

Los tests unitarios asociados a este flujo deberán verificar:

1. Que `register`, `edit` y `deleteDocument` llaman correctamente a la API y propagan los errores.
2. Que `get` y `getOne` transforman los datos de la API en un formato que la UI entiende.
3. Que los permisos (`role`, `permissions`) limitan correctamente las operaciones disponibles en la UI.
4. Que la lógica de descarga/visualización de adjuntos se comporta como se espera cuando:
   - Hay adjuntos válidos.
   - No existe ningún adjunto.
