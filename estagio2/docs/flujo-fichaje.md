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

**Contexto de fecha (`provider/date.js`)**

En ambos entornos existe un `DateContext` que centraliza la fecha seleccionada y algunos derivados de calendario.

Propiedades y helpers expuestos (versión escritorio):

- **Estado base**
  - `selectedDate`: fecha actualmente seleccionada.  
    - Se inicializa con `dayjs().toDate()`.
  - `selectedYear`: año actualmente seleccionado (por defecto, el año actual).
  - `setDate(date)`: actualiza `selectedDate` y sincroniza `selectedYear` con el año de la fecha.
  - `setYear(year)`: permite cambiar manualmente el año (por ejemplo, desde un selector de año).

- **Flags de conveniencia**
  - `isCurrentYear`: `true` si el año seleccionado coincide con el año actual.
  - `isCurrentMonth`: `true` si el mes seleccionado coincide con el mes actual.
  - `isCurrentDay`: `true` si el día seleccionado coincide con el día de hoy.

- **Semana ISO (lunes–domingo)**
  - `rangeWeek`: objeto `{ start, end }` con:
    - `start`: lunes de la semana ISO de la fecha seleccionada, en formato `YYYY-MM-DD`.
    - `end`: domingo de esa misma semana, en formato `YYYY-MM-DD`.
  - `setRangeWeek`: permite ajustar manualmente el rango cuando sea necesario.

- **Helpers de mes (solo escritorio)**
  - `getSelectedMonth()`: devuelve el mes seleccionado en formato `"YYYY-MM"` (sin espacios).
  - `getSelectedMonthISO()`: devuelve el **primer día del mes** como `"YYYY-MM-DD"`.
  - `getSelectedMonthRangeISO()`: devuelve `{ start, end }` con el rango completo del mes en ISO (`YYYY-MM-DD`).

En **móvil** (`provider/date.js` versión mobile):

- La API pública del contexto es compatible para el flujo de fichaje:
  - Existen: `selectedDate`, `setDate`, `selectedYear`, `setYear`, `isCurrentMonth`, `isCurrentDay`, `isCurrentYear`, `getSelectedMonth`, `rangeWeek`, `setRangeWeek`.
- Diferencias relevantes:
  - `getSelectedMonth()` devuelve `dayjs(selectedDate).format("YYYY-MM ")` → **incluye un espacio final**.
  - No existen los helpers `getSelectedMonthISO` ni `getSelectedMonthRangeISO`.

> **Uso específico en el fichaje:**  
> En el flujo de fichaje manual se utiliza `selectedDate` (no el string de `getSelectedMonth`) para construir las fechas completas de los registros y validar si la fecha es futura.  
> `rangeWeek` se usa para vistas que trabajan con semanas (por ejemplo, cuadrículas semanales), pero no altera las reglas de validación de fichaje.

---

### 1.3 Contexto de fichaje (`CheckinProvider`)

- Hook: `useCheckin` (basado en `CheckinProvider`).
- Responsabilidades principales:
  - Cargar los registros del día mediante `CheckinService.getByDate({ date })`.
  - Exponer, entre otros, los siguientes valores:
    - `timesForDay`: fichajes del día.
    - `lastTimeForDay`: último fichaje registrado (estado actual de entrada/salida).
    - `loadingTimes`: indicador de carga.
    - `refreshTimes()`: función para volver a consultar los fichajes tras un alta o modificación.

Este contexto es consumido por componentes como `CheckInDaily`, `EstadoActual`, `DailyRecords` y `TotalTimeCalculatorForDay`.

---

## 2. Acción de fichar (modo normal: `CheckInDaily`)

**Componente principal:** `imports/ui/components/Fichar/CheckInDaily.jsx`.

Responsabilidades:

1. **Consumo del contexto de fichaje**
   - Obtiene desde `useCheckin()`:
     - `timesForDay`.
     - `lastTimeForDay`.
     - `loadingTimes`.

2. **Estado de carga**
   - Mientras `loadingTimes` es `true`, el componente muestra:
     - `"Cargando..."` centrado.

3. **Botón de fichaje**
   - Renderiza `ButtonRegister` con:
     - `isEntry`: se calcula a partir de `lastTimeForDay?.status`:
       - Si existe `lastTimeForDay`, se usa su `status`.
       - Si no existe, se asume `'1'` (estado inicial, por ejemplo, primera entrada).
     - `selectedProject`: proyecto seleccionado, si la empresa trabaja con proyectos.

4. **Selector de proyectos**
   - Utiliza el hook `usePermissions()` para obtener `permissions`.
   - Si `permissions.allowProjects` es `true`:
     - Muestra `ProjectSelector` con:
       - Lista de proyectos (`projects`).
       - Proyecto actual (`selectedProject` / `setSelectedProject`).
       - Estado de carga combinado (`loadingProjects || loadingTimes`).
       - `timesForDay={lastTimeForDay}` como referencia de estado.

5. **Estado actual del día**
   - Muestra `EstadoActual` pasando `timesForDay={lastTimeForDay}` para reflejar el último fichaje registrado (entrada abierta, jornada cerrada, etc.).

6. **Modo home (uso en página de inicio)**
   - Cuando `CheckInDaily` se monta con la prop `home = true`:
     - Renderiza:
       - `DailyRecords` con `timesForDay` (histórico del día).
       - `TotalTimeCalculatorForDay` con `lastTimeForDay` (tiempo acumulado del día), si existe.

> En el **modo normal** no hay inputs de hora en la UI.  
> El componente delega la lógica de creación del fichaje y sus validaciones internas en `ButtonRegister` y en el backend a través de `CheckinService`.

---

## 3. Acción de fichar (modo manual: `EntradaManual`)

**Componente:** `imports/ui/components/Fichar/components/EntradaManual.jsx`.

Este componente permite registrar manualmente un intervalo de tiempo (entrada/salida) para la fecha seleccionada.

### 3.1 Gestión de fecha (`useDate`)

- Obtiene la fecha actual de trabajo desde el contexto:
  - `const { selectedDate: date } = useDate();`
- Usos concretos:
  - Construir las horas completas:
    - `base = dayjs(date).format('YYYY-MM-DD')`.
    - `hourStart = `${base}T${startTime}:00``.
    - `hourEnd = `${base}T${endTime}:00``.
  - Validar que **no se pueda registrar tiempo en una fecha futura**:
    - `selectedDay = dayjs(date).startOf('day')`.
    - `today = dayjs().startOf('day')`.
    - Si `selectedDay.isAfter(today)` → error:  
      `No se puede registrar tiempo en una fecha futura.`

> Esto garantiza que el usuario solo pueda registrar fichajes manuales para hoy o días pasados, independientemente de divergencias de formato (`getSelectedMonth`) entre web y móvil.

---

### 3.2 Gestión de proyectos (`useProjects` y permisos)

- Usa `useProjects()` para gestionar:
  - `projects`: listado de proyectos disponibles.
  - `loadingProjects`: estado de carga de proyectos.
  - `selectedProject`, `setSelectedProject`: proyecto actualmente seleccionado.
- Usa `usePermissions()` para comprobar:
  - `permissions.allowProjects`:
    - Si es `true`, se muestra el componente `ProjectSelector`.
    - En el payload del backend, el proyecto solo se añade cuando:
      - Hay permisos (`allowProjects`).
      - Existe un proyecto seleccionado (`selectedProject?.value`).

---

### 3.3 Estado local del formulario

- Inputs de hora:
  - `startTime`: hora de entrada (`type="time"`).
  - `endTime`: hora de salida (`type="time"`).
- Otros estados:
  - `loading`: bloquea el botón de envío mientras se procesa la petición.

---

### 3.4 Validación previa en frontend: `validateBeforeSubmit()`

Antes de contactar con el backend, se ejecuta `validateBeforeSubmit()` con estas reglas:

1. **Horas obligatorias**
   - Si falta `startTime` o `endTime`:
     - Se muestra un `toast.error('Debes indicar hora de entrada y salida.')`.
     - La función devuelve `false`.

2. **Orden de horas**
   - Se construyen dos instantes utilizando la misma fecha base:
     - `start = dayjs(`${base}T${startTime}:00`)`.
     - `end = dayjs(`${base}T${endTime}:00`)`.
   - Si `end` **no es posterior** a `start`:
     - Se muestra: `La hora de salida debe ser posterior a la de entrada.`.
     - La función devuelve `false`.

3. **Fecha no futura (comparación a nivel de día)**
   - Se comparan los días normalizados:
     - `selectedDay = dayjs(date).startOf('day')`.
     - `today = dayjs().startOf('day')`.
   - Si `selectedDay.isAfter(today)`:
     - Se muestra: `No se puede registrar tiempo en una fecha futura.`.
     - La función devuelve `false`.

4. **Resultado**
   - Si todas las validaciones pasan:
     - `validateBeforeSubmit()` devuelve `true`.
   - Si alguna falla:
     - Se aborta el flujo y **no se llama** al backend.

---

### 3.5 Comprobaciones adicionales antes de registrar (`setTimeToday`)

Función principal de envío al backend:

1. **Sesión válida**
   - Llama a `AuthService.isAuthenticated()`.
   - Si la respuesta no contiene `code === '200'`:
     - Redirige a `/login` mediante `useNavigate`.
     - No continúa con el registro.

2. **Reforzar reglas de negocio en cliente**
   - Se ejecuta `validateBeforeSubmit()`.
   - Si devuelve `false`, se corta la ejecución sin contactar con el servidor.

3. **Construcción del payload**
   - Se calcula la base de fecha: `base = dayjs(date).format('YYYY-MM-DD')`.
   - Payload mínimo:
     ```js
     {
       hourStart: `${base}T${startTime}:00`,
       hourEnd: `${base}T${endTime}:00`,
       // project: ... (opcional)
     }
     ```
   - Si `permissions.allowProjects` es `true` y hay proyecto seleccionado:
     - Se añade `project: selectedProject?.value ?? null`.

4. **Verificación de dispositivo (si aplica)**
   - Si `permissions.allowDeviceRegistration` es `true`:
     - Obtiene un `deviceId` con `getStoredDeviceId()`.
     - Valida el dispositivo con `DeviceService.check(deviceId)`.
     - Si la verificación no es satisfactoria:
       - Muestra:  
         `Dispositivo no verificado. No se puede registrar el tiempo.`
       - No llama a `CheckInService.registerManual`.

---

### 3.6 Registro en backend y efectos posteriores

1. **Llamada al servicio**
   - Se invoca `CheckInService.registerManual(payload)`.

2. **Caso exitoso**
   - Muestra un `toast.success(setTimeForBack.message)` con el mensaje devuelto por el backend.
   - Llama a `refreshTimes()` (desde `useCheckin`) para recargar:
     - `timesForDay`.
     - `lastTimeForDay`.
   - Limpia la selección de proyecto:
     - `setSelectedProject(false)`.

3. **Gestión de errores**
   - Cualquier excepción en el proceso se captura con `catch(error)`:
     - Registra el error en consola (`console.error(...)`).
     - Muestra:  
       `No se pudo registrar el tiempo. Inténtalo de nuevo.` mediante `toast.error`.

4. **Reseteo de estado de UI (bloque `finally`)**
   - `setLoading(false)`.
   - `setStartTime('')`.
   - `setEndTime('')`.

---

### 3.7 Comportamiento al montar el componente

- En `useEffect(() => { ... }, [])`:
  - Se llama a `setSelectedProject(false)` al montar.
  - Objetivo: garantizar que el formulario de entrada manual se abre **sin proyecto preseleccionado**, evitando arrastrar selecciones previas de otros flujos.

---

## 4. Permisos y efectos secundarios (resumen global)

- `usePermissions()` centraliza la lógica de permisos:
  - `allowManual`:
    - Se usa en componentes superiores (por ejemplo, `IndexRegister`) para mostrar u ocultar `EntradaManual`.
  - `allowProjects`:
    - En `CheckInDaily`: decide si se muestra `ProjectSelector`.
    - En `EntradaManual`: decide si se muestra el selector y si el payload lleva campo `project`.
  - `allowDeviceRegistration`:
    - En `EntradaManual`: obliga a validar el dispositivo (`DeviceService.check`) antes de registrar fichajes manuales.

- Efectos secundarios tras un registro correcto:
  - `refreshTimes()` recarga el contexto de fichaje.
  - Los componentes que dependen de `useCheckin` se actualizan automáticamente:
    - `CheckInDaily`, `EstadoActual`, `DailyRecords`, `TotalTimeCalculatorForDay`, etc.

---

## 5. Errores gestionados

### 5.1 Validaciones de UI (cliente)

- Se gestionan mediante `react-toastify`:
  - Campos obligatorios (horas de entrada y salida).
  - Orden de las horas (salida debe ser posterior a entrada).
  - Fecha futura no permitida.
  - Dispositivo no verificado (cuando hay registro de dispositivo).

### 5.2 Errores de servicio (`CheckinService`)

- Errores en `registerManual`:
  - Se capturan en el `catch` de `setTimeToday`.
  - Se muestra un mensaje genérico:  
    `No se pudo registrar el tiempo. Inténtalo de nuevo.`

- Errores en `getByDate` y `getByDates`:
  - El servicio devuelve un payload con `code` y `message` cuando se trata de errores controlados.
  - La UI debe mostrar estos mensajes para que el usuario mantenga contexto.

---

## 6. Cobertura prevista con tests unitarios

Los tests unitarios relacionados con el fichaje deberían cubrir, como mínimo:

1. **Servicios de fichaje (`CheckinService`)**
   - Que `registerManual`:
     - Propaga excepciones cuando la API falla.
   - Que `getByDate` y `getByDates`:
     - Devuelven correctamente el payload de error (`code`, `message`).
     - La UI interpreta dichos errores y no deja al usuario sin feedback.

2. **Validaciones de `EntradaManual`**
   - Bloqueo del envío cuando:
     - Falta la hora de entrada o la de salida.
     - La hora de salida es anterior o igual a la hora de entrada.
     - La fecha seleccionada es futura (comparación a nivel de día).
   - Comportamiento con permisos:
     - Con `allowDeviceRegistration = true`:
       - Si `DeviceService.check` devuelve un valor falsy → **no** se llama a `CheckInService.registerManual` y se muestra el error correspondiente.
     - Con `allowProjects = true`:
       - El payload incluye el campo `project` con el identificador del proyecto (`selectedProject.value`).
   - Efectos posteriores a un fichaje correcto:
     - Se llama a `refreshTimes()`.
     - Se limpian `startTime`, `endTime` y la selección de proyecto.

3. **Integración de `CheckInDaily` con el contexto**
   - Que muestre `"Cargando..."` mientras `loadingTimes` es `true`.
   - Que pase correctamente `isEntry` a `ButtonRegister` según `lastTimeForDay.status`.
   - Que muestre u oculte `ProjectSelector` en función de `permissions.allowProjects`.
   - Que, en modo `home`, renderice `DailyRecords` y `TotalTimeCalculatorForDay` cuando proceda.

---

## 7. Divergencias actuales en el manejo de fechas (`provider/date.js`)

Aunque el flujo de fichaje descrito aquí funciona en ambas versiones, existen divergencias relevantes entre escritorio y móvil que conviene documentar.

### 7.1 Diferencias detectadas

- **Versión escritorio (`DateProvider` web):**
  - `getSelectedMonth()` devuelve el mes en formato **ISO limpio**: `"YYYY-MM"` (sin espacios).
  - Existen helpers adicionales:
    - `getSelectedMonthISO()` → primer día del mes como `"YYYY-MM-DD"`.
    - `getSelectedMonthRangeISO()` → rango completo del mes `{ start, end }` (ambos `"YYYY-MM-DD"`).
  - `rangeWeek` se calcula a partir de `dayjs(selectedDate).startOf('isoWeek')` / `endOf('isoWeek')`.

- **Versión móvil (`DateProvider` móvil):**
  - `getSelectedMonth()` devuelve `"YYYY-MM "` (con un **espacio final**).
  - No dispone de `getSelectedMonthISO()` ni de `getSelectedMonthRangeISO()`.
  - `rangeWeek` también se calcula usando `startOf('isoWeek')` y `endOf('isoWeek')`, por lo que el rango semanal sí está alineado entre ambas versiones.

### 7.2 Impacto en el flujo de fichaje

- El flujo de fichaje manual utiliza:
  - `selectedDate` (objeto Date/compatible con `dayjs`) para:
    - Construir las fechas completas de fichaje.
    - Validar si la fecha es futura.
- Por tanto:
  - El espacio extra en `"YYYY-MM "` afecta al helper `getSelectedMonth()`, pero **no** a las validaciones de fichaje, que trabajan directamente con `selectedDate` y comparaciones de día (`startOf('day')`).
  - Los helpers ISO adicionales de escritorio aún no son imprescindibles para el flujo de fichaje, aunque sí resultan útiles para vistas de calendario y para la batería de tests.

### 7.3 Decisión futura (Etapas posteriores)

- **Normalizar** el formato de `getSelectedMonth()` en ambas versiones a `"YYYY-MM"` (sin espacios).
- **Compartir** los helpers ISO (`getSelectedMonthISO`, `getSelectedMonthRangeISO`) entre escritorio y móvil, de forma que cualquier lógica de calendario (incluido el fichaje) pueda utilizar la misma API de fechas en todos los entornos.

Con esta documentación, el flujo de fichaje queda descrito de forma coherente para web y móvil, y se dejan identificados los puntos de divergencia que deberán resolverse en las siguientes etapas de unificación de ramas.
