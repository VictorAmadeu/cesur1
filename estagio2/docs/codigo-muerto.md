# Código potencialmente muerto y duplicidades (Etapa 1 – Preparación)

> IMPORTANTE: en la Etapa 3 **sólo se identifican** candidatos a código muerto o duplicado.  
> No se elimina nada del repositorio; la limpieza se hará en la Etapa 2, cuando exista una
> batería mínima de tests que nos proteja.

---

## 1. Hooks y utilidades potencialmente sin uso

### 1.1 `useCheckinTimes.js`

- Descripción: hook pensado para calcular tiempos de fichaje agregados.
- Situación actual:
  - No se encuentra referenciado desde los componentes principales de fichaje
    (`CheckInDaily`, `EntradaManual`, `DailyRecords`, etc.).
  - La lógica de cálculo de tiempos parece duplicada en otros componentes
    y providers.
- Decisión (Etapa 3):
  - Mantener el archivo sin cambios.
  - Marcarlo como **candidato a eliminación o refactor** en la Etapa 2,
    una vez que haya tests sobre el flujo de fichaje.

---

## 2. Duplicidades en lógica de negocio y utilidades

### 2.1 Descarga en web vs móvil

- Componentes implicados:
  - `imports/ui/components/Documento/DocumentosWeb.jsx`
  - `imports/ui/components/Documento/DocumentosMobile.jsx`
- Situación actual:
  - Cada componente implementa su propia lógica de descarga basándose en
    datos en base64.
  - Ambos realizan conversiones y disparan descargas con pasos muy
    similares, adaptando sólo el detalle a web o móvil.
- Decisión (Etapa 3):
  - Documentar la duplicidad y mantener el código tal cual.
  - Proponer para la Etapa 2:
    - Extraer un hook o utilidad común de descarga (por ejemplo,
      `useDocumentDownload`) que reciba los datos y decida el mecanismo
      según el entorno (web/móvil).

### 2.2 Identificación de dispositivo (`deviceUtils`)

#### Situación actual

**Versión escritorio – `imports/utils/deviceUtils.js`**

- Expone la función asíncrona `getStoredDeviceId()`.
- Fuentes de almacenamiento consultadas, en este orden:
  1. `localforage.getItem("deviceId")`
  2. Cookie `deviceId_backup`
  3. `localStorage.getItem("deviceId_backup")`
  4. `sessionStorage.getItem("deviceId_backup")`
- Devuelve siempre un **objeto estructurado**:
  - Caso exitoso:
    ```js
    {
      code: 200,
      status: "success",
      deviceId: <valor_encontrado>,
      source: "localforage" | "cookies" | "localStorage" | "sessionStorage"
    }
    ```
  - Caso sin resultados:
    ```js
    {
      code: 404,
      status: "error",
      message: "Device ID no encontrado en ningún almacenamiento",
      deviceId: null
    }
    ```

**Versión móvil – `imports/utils/deviceUtils.js` (Cordova)**

- Expone dos utilidades principales:
  - `getDeviceId()`: lee un identificador de dispositivo.
  - `createAndStoreDeviceId()`: genera y persiste un nuevo identificador.
- Fuentes y estrategia:
  - Si la app corre en entorno Cordova y existe `window.NativeStorage`:
    - Usa `NativeStorage.getItem('deviceId', ...)` para leer.
    - Usa `NativeStorage.setItem('deviceId', deviceId, ...)` para guardar.
  - En otros casos (por ejemplo, emulador sin plugin):
    - Usa `localforage.getItem('deviceId')` / `localforage.setItem('deviceId', deviceId)`.
- El identificador se genera mediante `uuidv4()` y se devuelve como **string** (o `null`), sin objeto de metadatos.

#### Riesgos y puntos de atención

- **Interfaces distintas**:
  - Escritorio devuelve un objeto con `code`, `status`, `deviceId` y `source`.
  - Móvil devuelve directamente el valor (`string` o `null`) y, en el caso de creación, no expone metadatos adicionales.
- **Lógica parcialmente duplicada**:
  - Ambos módulos resuelven el mismo problema: identificar de forma estable el dispositivo.
  - Cada uno lo hace con mecanismos diferentes (cookies / `localStorage` / `sessionStorage` / `localforage` frente a `NativeStorage` / `localforage`), sin una API común.
- **Riesgo de divergencia futura**:
  - Si se modifica la política de almacenamiento del ID de dispositivo en una versión (por ejemplo, cambiar la clave o la prioridad de fuentes), es fácil que la otra quede desalineada.
  - Los componentes que consumen estas utilidades deben conocer detalles de implementación diferentes según el entorno (web vs Cordova).

#### Decisión (Etapa 3 – sólo identificación)

- Mantener **ambas implementaciones** sin cambios en código.
- Documentar las diferencias como **duplicidad controlada** (no código muerto).
- Marcar la identificación de dispositivo como candidata a:
  - **Unificación de API** (firma de retorno homogénea).
  - **Encapsulación por entorno** (web vs Cordova) para reducir condicionales dispersos en los componentes.

#### Propuesta para la Etapa 2 (no ejecutar todavía)

- Diseñar una API común de dispositivo que:
  - Detecte el entorno (`Meteor.isCordova`, presencia de `NativeStorage`, etc.).
  - Delegue en la implementación adecuada:
    - Web/escritorio: `localforage`, cookies y `sessionStorage` / `localStorage`.
    - Móvil/Cordova: `NativeStorage` con respaldo en `localforage`.
  - Unifique la firma de retorno (por ejemplo, siempre un objeto con `code`, `status`, `deviceId` y `source`), de forma que los componentes no tengan que conocer las diferencias internas.
- Una vez existan tests que cubran:
  - Registro de dispositivo.
  - Validaciones previas a fichar (cuando el dispositivo debe estar verificado).
  - Escenarios web y móviles.
  
  se podrá:
  - Extraer la lógica de acceso a almacenamiento a una única capa.
  - Reducir duplicidades y condicionales específicos de entorno en los componentes de ficha y registro de dispositivo.

---

## 3. Cómo se han detectado estos candidatos

- Revisión de la documentación de flujos críticos (login, fichaje, ausencias,
  descargas, identificación de dispositivo) y comparación con el código real.
- Búsqueda global en el proyecto (`Ctrl + Shift + F` en VS Code) para localizar
  referencias a hooks/utilidades sospechosas (por ejemplo, `useCheckinTimes`,
  `deviceUtils`, lógica de descarga en componentes de documentos).
- Revisión manual de componentes que contienen lógica similar
  (por ejemplo, varias implementaciones de descarga de documentos o lectura de ID
  de dispositivo en web y móvil).

---

## 4. Próximos pasos (para la Etapa 2, no ejecutar todavía)

1. **Escribir tests unitarios** que cubran:
   - Flujos de fichaje (incluyendo cálculo de tiempos).
   - Descarga de documentos (web y móvil).
   - Escenarios donde el dispositivo debe estar identificado/verificado antes de permitir determinadas acciones.

2. Una vez que los tests estén verdes:
   - Eliminar o refactorizar `useCheckinTimes.js` si se confirma que no se usa.
   - Extraer la lógica de descarga a un hook/utilidad compartida y borrar
     duplicados en `DocumentosWeb.jsx` y `DocumentosMobile.jsx`.
   - Diseñar e implementar una **API común de dispositivo** que envuelva las
     implementaciones actuales (`getStoredDeviceId`, `getDeviceId`,
     `createAndStoreDeviceId`), permitiendo reducir duplicidades y mantener
     un comportamiento alineado entre escritorio y móvil.

3. Ejecutar `npm test` después de cada cambio para asegurarse de que la
   cobertura sigue pasando y no se rompe nada en producción.
