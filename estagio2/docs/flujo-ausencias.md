# Flujo de ausencias/licencias: Reglas de negocio que deberán cubrir los tests unitarios.

1. **UI**: las ausencias se gestionan desde los componentes `imports/ui/components/Ausencia/*` y la página `imports/ui/pages/Ausencias.jsx`.
2. **Servicios**: `LicenseService` ofrece métodos para:
   - `get` – obtener licencias por año.
   - `getOne` – obtener el detalle de una licencia y sus documentos.
   - `register` – crear nuevas licencias, con o sin adjuntos (archivos en base64).
   - `edit` – modificar licencias existentes (fechas, comentarios, adjuntos).
   - `deleteDocument` – eliminar adjuntos específicos.
   - `pendingSummary` y `pendingList` – exponer licencias pendientes de aprobación para supervisores.
3. **Permisos/Roles**: se leen desde `PermissionsContext` y las cookies (`role`, `permissions`).
4. **Descarga/visualización de adjuntos**: se basa en los datos devueltos por `getOne` y la UI específica. Se recomienda extraer la lógica de descarga a un hook reutilizable.
5. **Errores**: `licenseService` propaga excepciones y devuelve `response.data` en los casos felices. Los tests cubren las rutas felices; se pueden ampliar con casos de error.

Recomendación: mantener las validaciones de fechas y tipos en la UI y duplicarlas en el backend.
