# Flujo de fichaje (registro de tiempo)

> **Objetivo:** describir el flujo completo de fichaje en Intranek (web/móvil), cómo se gestiona la fecha seleccionada, las validaciones de negocio y los puntos donde se apoyarán los tests unitarios.

---

## 1. Entrada, fecha seleccionada y contextos

### 1.1 Punto de entrada (UI)

- Página principal: `imports/ui/pages/RegistrarTiempo.jsx`.
- Esta página monta el componente `IndexRegister`, que a su vez:
  - Muestra el bloque de fichaje diario (`CheckInDaily`).
  - Muestra el bloque de **entrada manual** (`EntradaManual`) cuando los permisos lo permiten (`allowManual`).

---

### 1.2 Selector de fecha y `DateProvider` (web y móvil)

**Componente de UI**
- `DatePickerSelect` es el selector visual de fecha.
- Cada cambio de fecha actualiza el contexto de fecha (`DateProvider`), común a web y móvil.

**Contexto de fecha (`imports/provider/date.js`)**
En ambos entornos existe un `DateContext` que centraliza la fecha seleccionada y utilidades de calendario.

Propiedades y helpers expuestos (unificado):

- **Estado base**
  - `selectedDate`: fecha seleccionada (se inicializa con la fecha actual).
  - `selectedYear`: año seleccionado.
  - `setDate(date)`: actualiza `selectedDate` y sincroniza `selectedYear`.
  - `setYear(year)`: cambia manualmente el año.

- **Flags de conveniencia**
  - `isCurrentYear`, `isCurrentMonth`, `isCurrentDay`.

- **Semana ISO (lunes–domingo)**
  - `rangeWeek`: `{ start, end }` en `YYYY-MM-DD`.
  - `setRangeWeek`.

- **Helpers de mes (compatibilidad + robustez)**
  - `getSelectedMonth()` → `"YYYY-MM"` (contrato legacy).
  - `getSelectedMonthISO()` → `"YYYY-MM-DD"` (primer día del mes).
  - `getSelectedMonthRangeISO()` → `{ start, end }` en `"YYYY-MM-DD"`.

> **Uso específico en fichaje:**  
> En el fichaje manual se usa `selectedDate` para construir fechas completas y validar “no futuro”.  
> Esto evita dependencias de strings de mes (`getSelectedMonth`) para reglas de negocio.

---

### 1.3 Contexto de fichaje (`CheckinProvider`)

- Hook: `useCheckin` (basado en `CheckinProvider`).
- Responsabilidades:
  - Cargar registros del día mediante `CheckinService.getByDate({ date })`.
  - Exponer:
    - `timesForDay`
    - `lastTimeForDay`
    - `loadingTimes`
    - `refreshTimes()` (recarga tras registrar/modificar)

Este contexto es consumido por:
- `CheckInDaily`, `EstadoActual`, `DailyRecords`, `TotalTimeCalculatorForDay`, etc.

---

## 2. Acción de fichar (modo normal: `CheckInDaily`)

**Componente principal:** `imports/ui/components/Fichar/CheckInDaily.jsx`.

Responsabilidades:

1. **Consumo del contexto**
   - `timesForDay`, `lastTimeForDay`, `loadingTimes`.

2. **Estado de carga**
   - Si `loadingTimes` es `true`, muestra `"Cargando..."`.

3. **Botón de fichaje**
   - Renderiza `ButtonRegister` con:
     - `isEntry` según `lastTimeForDay?.status` (si no hay, estado inicial).
     - `selectedProject` si aplica.

4. **Selector de proyectos**
   - Si `permissions.allowProjects` es `true`:
     - Muestra `ProjectSelector`.

5. **Estado actual**
   - Muestra `EstadoActual` para reflejar el último fichaje.

6. **Modo home**
   - Si `home = true`, renderiza:
     - `DailyRecords`
     - `TotalTimeCalculatorForDay` (si procede)

> En modo normal no hay inputs de hora: la lógica de creación/validación principal recae en `ButtonRegister` y el backend.

---

## 3. Acción de fichar (modo manual: `EntradaManual`)

**Componente:** `imports/ui/components/Fichar/components/EntradaManual.jsx`.

Permite registrar manualmente un intervalo de tiempo (entrada/salida) para la fecha seleccionada.

### 3.1 Inputs y estado local

- Inputs:
  - `startTime` (`type="time"`)
  - `endTime` (`type="time"`)
- Estado:
  - `loading` (bloqueo durante envío)
  - (opcional según UI) flag para deshabilitar botón y evitar dobles clics

---

### 3.2 Validación previa en frontend: `validateBeforeSubmit()`

Antes de contactar con el backend, se ejecuta `validateBeforeSubmit()` con reglas robustas:

1. **Horas obligatorias**
   - Si falta `startTime` o `endTime`:
     - Error en UI (toast) y retorno `false`.

2. **Orden de horas**
   - Se construyen instantes con la fecha seleccionada:
     - `base = dayjs(date).format('YYYY-MM-DD')`
     - `start = dayjs(`${base}T${startTime}:00`)`
     - `end = dayjs(`${base}T${endTime}:00`)`
   - Si `end` no es posterior a `start`:
     - Error y retorno `false`.

3. **Diferencia mínima (>= 1 minuto)**
   - Queda garantizada al exigir `end.isAfter(start)` con inputs `HH:mm`.
   - Si son iguales, la validación falla (no hay mínimo de 1 minuto).

4. **Fecha no futura**
   - Comparación a nivel de día:
     - `selectedDay = dayjs(date).startOf('day')`
     - `today = dayjs().startOf('day')`
   - Si `selectedDay.isAfter(today)`:
     - Error y retorno `false`.

Si todo pasa:
- Retorna `true`.

---

### 3.3 Flujo principal de envío: `setTimeToday()`

1. **Sesión válida**
   - `AuthService.isAuthenticated()`
   - Si no es válida (`code !== '200'`):
     - Navega a `/login` y aborta.

2. **Validaciones de negocio en cliente**
   - Ejecuta `validateBeforeSubmit()`
   - Si falla, aborta sin llamar al backend.

3. **Permisos**
   - Se leen desde `usePermissions()`:
     - `allowProjects`
     - `allowDeviceRegistration`

4. **DeviceId (unificado)**
   - Si `allowDeviceRegistration` es `true`:
     - Se obtiene `deviceId` desde la utilidad unificada de dispositivo (por ejemplo, `getOrCreateDeviceId()` o la API equivalente definida en `imports/utils/deviceUtils`).
     - Se verifica con `DeviceService.check(deviceId)`.
     - Si no está verificado:
       - Error y aborta (no llama a `registerManual`).

5. **Construcción del payload**
   - Base de fecha: `base = dayjs(date).format('YYYY-MM-DD')`
   - Payload mínimo:
     ```js
     {
       hourStart: `${base}T${startTime}:00`,
       hourEnd: `${base}T${endTime}:00`,
     }
     ```
   - Si `allowProjects` es `true`:
     - Añade `project: selectedProject?.value ?? null`
   - Si `allowDeviceRegistration` es `true`:
     - Añade `deviceId: deviceId ?? null`

6. **Registro en backend**
   - `CheckInService.registerManual(payload)`

7. **Post-éxito**
   - `toast.success(...)`
   - `refreshTimes()` para recargar el contexto de fichaje.
   - `setSelectedProject(false)` para limpiar selección.

8. **Finally (reset UI)**
   - `setLoading(false)`
   - Limpia `startTime` y `endTime`
   - Rehabilita botón si aplica

---

### 3.4 Comportamiento al montar

- En `useEffect(() => { ... }, [])`:
  - `setSelectedProject(false)`
  - Garantiza que el formulario abre sin proyecto preseleccionado.

---

## 4. Permisos y efectos secundarios (resumen global)

- `allowManual`: decide si se muestra `EntradaManual`.
- `allowProjects`:
  - Muestra `ProjectSelector`.
  - Añade campo `project` al payload.
- `allowDeviceRegistration`:
  - Obliga a obtener `deviceId`.
  - Obliga a verificarlo con `DeviceService.check(deviceId)`.
  - Añade `deviceId` al payload de `registerManual`.

Tras un registro correcto:
- `refreshTimes()` actualiza `timesForDay`, `lastTimeForDay` y componentes dependientes.

---

## 5. Errores gestionados

### 5.1 Validaciones (cliente)

Se gestionan con `react-toastify`:

- Horas obligatorias.
- Salida posterior a entrada.
- No registrar en fecha futura.
- Dispositivo no verificado (si aplica).

### 5.2 Errores de servicio

- Errores en `registerManual`:
  - Se capturan y se notifica al usuario (toast).
  - No se deja la UI en estado inconsistente (se resetea en `finally`).

---

## 6. Cobertura prevista con tests unitarios

1. **Validaciones de `EntradaManual`**
   - Bloquea envío si:
     - Falta `startTime` o `endTime`.
     - `endTime` <= `startTime`.
     - Fecha futura.
   - Con `allowProjects = true`:
     - El payload incluye `project`.
   - Con `allowDeviceRegistration = true`:
     - Se obtiene `deviceId`.
     - Si `DeviceService.check(deviceId)` falla → no llama a `registerManual`.
     - Si pasa → payload incluye `deviceId`.

2. **Servicios (`CheckinService`)**
   - `registerManual` maneja OK y errores conforme a contrato existente.

3. **Integración con contexto**
   - Tras éxito:
     - Llama a `refreshTimes()`.
     - Limpia inputs y selección de proyecto.
