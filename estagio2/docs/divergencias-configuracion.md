# Divergencias de configuración entre ramas (web vs móvil)

> Objetivo: documentar las diferencias de configuración y comportamiento entre la rama de escritorio\
> (`master` / `victor/unir-ramas-desktop`) y la rama móvil (`Develop-Mobile` / `victor/unir-ramas-mobile`)\
> para preparar una futura unificación sin riesgo para producción.

---

## 1. Alcance del documento

Este documento recoge:

- Diferencias de configuración *técnica* (clientes HTTP, utilidades de fecha, device utils, etc.).
- Divergencias de comportamiento en flujos críticos (fichaje, login).
- Componentes duplicados entre web y móvil.

**Importante:**\
Este documento **no introduce cambios de código**. Solo describe el estado actual de cada rama para que, en etapas posteriores, se puedan tomar decisiones informadas sin romper producción.

---

## 2. `axiosClient.js` (cliente HTTP)

Ruta: `imports/service/axiosClient.js` en ambas ramas.

### 2.1 Versión escritorio (rama web: `victor/unir-ramas-desktop`)

```js
import axios from "axios";
import Cookies from "js-cookie";
import { Meteor } from "meteor/meteor";

// Crear una instancia de Axios
const axiosClient = axios.create({
  baseURL: Meteor.settings.public.baseUrl,
  timeout: 30000, // Tiempo límite para las solicitudes
});

// Agregar un interceptor para incluir el token automáticamente
axiosClient.interceptors.request.use((config) => {
  const token = Cookies.get("tokenIntranEK");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
}, (error) => {
  return Promise.reject(error);
});

// Manejar respuestas globales
axiosClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      console.error("Sesión expirada. Por favor, inicia sesión nuevamente.");
    }
    return Promise.reject(error);
  }
);

export default axiosClient;
```

**Configuración relevante (web):**

- `baseURL`: `Meteor.settings.public.baseUrl`.
- `timeout`: `30000 ms`.
- `withCredentials`: **no está configurado explícitamente** (se usa el valor por defecto de Axios).
- **Cabeceras por defecto en la instancia**:

  - No se definen cabeceras iniciales en `axios.create`.
- **Cabeceras en el interceptor de petición**:

  - Si existe cookie `tokenIntranEK`, se añade:

    - `Authorization: Bearer <token>`.
  - No se modifican otras cabeceras.
- **Interceptor de respuesta**:

  - Si la respuesta es `401`, se registra en consola:

    - `"Sesión expirada. Por favor, inicia sesión nuevamente."`
  - El error se propaga con `Promise.reject(error)`.

---

### 2.2 Versión móvil (rama Cordova: `victor/unir-ramas-mobile`)

```js
import axios, { AxiosHeaders } from 'axios';
import Cookies from 'js-cookie';
import { Meteor } from 'meteor/meteor';

const axiosClient = axios.create({
  baseURL: Meteor.settings.public.baseUrl,
  timeout: 30000,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  }
});

axiosClient.interceptors.request.use(
  (config) => {
    if (!config.headers) {
      config.headers = new AxiosHeaders();
    }
    const token = Cookies.get('tokenIntranEK');
    if (token) {
      config.headers.set
        ? config.headers.set('Authorization', `Bearer ${token}`)
        : (config.headers.Authorization = `Bearer ${token}`);
    }
    return config;
  },
  (error) => Promise.reject(error)
);

axiosClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      console.error('Sesión expirada. Por favor, inicia sesión nuevamente.');
    }
    return Promise.reject(error);
  }
);

export default axiosClient;
```

**Configuración relevante (móvil):**

- `baseURL`: `Meteor.settings.public.baseUrl`.
- `timeout`: `30000 ms`.
- `withCredentials`: **`true`**, configurado explícitamente.
- **Cabeceras por defecto en la instancia**:

  - `Content-Type: application/json`
  - `Accept: application/json`
- **Cabeceras en el interceptor de petición**:

  - Si no existen cabeceras en `config`, se inicializan con `new AxiosHeaders()`.
  - Si existe cookie `tokenIntranEK`, se añade:

    - `Authorization: Bearer <token>`
    - Se utiliza `headers.set(...)` cuando está disponible (caso `AxiosHeaders`),
      o bien se asigna directamente `config.headers.Authorization`.
- **Interceptor de respuesta**:

  - Si la respuesta es `401`, se registra en consola:

    - `"Sesión expirada. Por favor, inicia sesión nuevamente."`
  - El error se propaga con `Promise.reject(error)`.

---

### 2.3 Resumen de diferencias en `axiosClient.js`

**Comunes en ambas ramas:**

- `baseURL` apunta a `Meteor.settings.public.baseUrl`.
- `timeout` fijado en `30000 ms`.
- Se lee el token desde la cookie `tokenIntranEK`.
- Se añade una cabecera `Authorization: Bearer <token>` cuando el token existe.
- En respuestas `401` se muestra un mensaje de sesión expirada y se propaga el error.

**Divergencias clave:**

1. **`withCredentials`**

   - **Web**: no se establece explícitamente (valor no definido en la instancia).
   - **Móvil**: se establece `withCredentials: true` en la configuración del cliente.

2. **Cabeceras iniciales de la instancia**

   - **Web**: no define cabeceras en `axios.create` (se usan las que pone Axios por defecto).
   - **Móvil**: fuerza:

     - `Content-Type: application/json`
     - `Accept: application/json`

3. **Gestión de `config.headers`**

   - **Web**: asume que `config.headers` existe y asigna directamente:

     - `config.headers.Authorization = 'Bearer <token>'`.
   - **Móvil**:

     - Si `config.headers` está vacío, crea `config.headers = new AxiosHeaders()`.
     - Usa `config.headers.set('Authorization', ...)` cuando está disponible.
     - Fallback a `config.headers.Authorization = ...` si `set` no existe.

4. **Importación de tipos**

   - **Web**: importa solo `axios`.
   - **Móvil**: importa `axios` y también `AxiosHeaders` para trabajar con cabeceras tipadas.

---

### 2.4 Notas y decisiones pendientes (no aplicadas todavía)

- La configuración móvil está más estrictamente tipada y está preparada para entornos donde:

  - Es necesario enviar cookies (`withCredentials: true`).
  - Se requiere forzar `Content-Type` y `Accept` como `application/json`.
- La versión web es más simple y deja la mayor parte de la configuración en manos de los valores por defecto del navegador/Axios.

**Para la etapa de unificación (futuro, sin aplicar aún):**

- Definir un cliente HTTP unificado que:

  - Mantenga la compatibilidad con Cordova / móvil cuando `withCredentials` y las cookies sean necesarias.
  - Permita compartir la lógica de cabeceras (especialmente `Authorization`) en ambos entornos.
  - Utilice una estrategia clara para inicializar `config.headers` (por ejemplo, siempre `AxiosHeaders` o una interfaz uniforme).
- Por ahora, **las dos implementaciones se mantienen tal cual** para no asumir requisitos de red específicos sin confirmación de backend / equipo de sistemas.

---

## 3. Validaciones de fichaje (CheckIn / Entrada manual)

**Situación actual:**

- La rama de escritorio dispone de validaciones más completas en el flujo de fichaje, incluyendo:

  - Control de horas vacías.
  - Comprobación de que la hora de salida es posterior a la de entrada.
  - Impedir registros en fechas futuras.
  - Reglas mínimas de diferencia de tiempo entre entrada y salida.
- En la rama móvil:

  - Parte de estas validaciones están:

    - Implementadas de forma *inline*.
    - O directamente ausentes en determinados puntos.
- Se ha introducido una función de validación explícita en escritorio (`validateBeforeSubmit`) que organiza y centraliza estas comprobaciones en la UI.

**Riesgo principal:**

- Diferencias de validación entre web y móvil pueden producir:

  - Comportamientos distintos para el mismo usuario según el dispositivo.
  - Datos inconsistentes en backend (ej. fichajes en fechas futuras si no se valida en móvil).

**Decisión pendiente (futuro, sin aplicar ahora):**

- Reutilizar una validación común (por ejemplo, `validateBeforeSubmit`) en ambos contextos (web y móvil), manteniendo las reglas clave en frontend y duplicadas en backend.

---

## 4. Login y uso de jQuery en móvil

**Divergencia detectada:**

- En la aplicación móvil:

  - Se reintrodujo **jQuery** para parte de la lógica de login.
  - Se perdió **la persistencia del correo electrónico** que sí está presente en la versión web (recordar email del usuario).

**Consecuencias:**

- Código menos coherente con el resto de la base (que está en React).
- Experiencia de usuario distinta:

  - Web recuerda el email (opción “recordarme”).
  - Móvil no mantiene el mismo comportamiento.

**Decisión pendiente (futuro, sin aplicar ahora):**

- Eliminar jQuery del flujo de login móvil.
- Restaurar la persistencia del correo usando exclusivamente React y el mismo patrón que en escritorio.

---

## 5. `date.js` y formato de fechas

**Divergencias:**

- Versión escritorio:

  - Devuelve fechas en un formato consistente (ISO-like) y dispone de **helpers** para trabajar con fechas.
- Versión móvil:

  - Devuelve fechas en formato `"YYYY-MM "` (con un espacio al final).
  - Carece de algunos **helpers ISO** presentes en escritorio.

**Riesgos:**

- Posibles errores sutiles al construir fechas (por ejemplo, al concatenar strings que contienen espacios).
- Diferencias en el formateo de fechas entre web y móvil.

**Decisión pendiente (futuro, sin aplicar ahora):**

- Normalizar el formato de salida (por ejemplo, sin espacios extra).
- Recuperar los helpers ISO en la implementación móvil.
- Mantener una API común de utilidades de fecha entre ambas ramas.

---

## 6. Device utils (identificación de dispositivo)

**Situación actual:**

- Versión web:

  - La identificación de dispositivo se gestiona mediante:

    - `localforage`
    - `cookies`
    - `sessionStorage`
- Versión móvil:

  - Genera un **UUID** y lo almacena vía **NativeStorage** (plugins Cordova).

**Implicaciones:**

- El mismo usuario puede ser identificado con mecanismos distintos según plataforma.
- Reglas de seguridad o licenciamiento ligadas al “device id” pueden comportarse de forma diferente.

**Decisión pendiente (futuro, sin aplicar ahora):**

- Definir una **API única de device utils** que:

  - Detecte el entorno (web vs Cordova).
  - Utilice internamente el mecanismo adecuado:

    - Web: localforage/cookies/sessionStorage.
    - Móvil: NativeStorage / plugin específico.
- Mantener una interfaz común hacia el resto de la aplicación.

---

## 7. Componentes duplicados (web vs móvil)

**Componentes identificados como duplicados / paralelos:**

- Documentos:

  - `DocumentosWeb.jsx` (web)
  - `DocumentosMobile.jsx` (móvil)
- Navegación:

  - `NavDesktop.jsx`
  - `NavMovil.jsx`
- Horario:

  - `ScheduleGridDesktop.jsx`
  - Vista móvil correspondiente (`Movil`, `MovilCard`, etc.)

**Riesgos:**

- Lógica de negocio replicada en dos componentes distintos.
- Cambios futuros que se apliquen solo a una versión (desktop o móvil) y queden desalineados.

**Decisión pendiente (futuro, sin aplicar ahora):**

- Planificar, en etapas posteriores, la creación de:

  - Componentes **responsive** o adaptativos.
  - O componentes base reutilizables con variaciones mínimas para web/móvil.
- Documentar qué ficheros se unificarán y cuáles seguirán siendo específicos de plataforma (si es necesario).

---

## 8. Resumen operativo

- Este documento **no modifica código**, solo describe el estado actual de ambas ramas.
- Las principales divergencias de configuración se concentran en:

  - `axiosClient.js` (withCredentials, cabeceras, gestión de headers).
  - Utilidades de fecha (`date.js`).
  - Device utils (identificación de dispositivo).
  - Uso de jQuery y persistencia de datos en el login móvil.
  - Componentes UI duplicados entre web y móvil.
- Las decisiones de unificación se posponen a etapas posteriores, una vez validadas las necesidades de:

  - Backend / API.
  - Cordova / entorno móvil.
  - Experiencia de usuario en ambos dispositivos.

Mientras tanto, esta documentación sirve como **mapa de divergencias** para reducir el riesgo cuando llegue el momento de unificar la base de código.

```
::contentReference[oaicite:9]{index=9}
```

---

## Componentes duplicados web/móvil

- Documentos:
  - Web: `imports/ui/components/Documento/DocumentosWeb.jsx`
  - Móvil: `imports/ui/components/Documento/DocumentosMobile.jsx`

- Navegación principal:
  - Web: `imports/ui/components/Layout/desktop/NavDesktop.jsx`
  - Móvil: `imports/ui/components/Layout/movil/NavMovil.jsx`

- Horario:
  - Web: `imports/ui/components/Horario/Desktop/ScheduleGridDesktop.jsx`
  - Móvil: [indicar fichero de la vista móvil equivalente]

Decisión futura:

- Diseñar componentes responsive o adaptativos que permitan compartir la lógica
  y reducir duplicación (por ejemplo, un solo componente que reciba `variant="web"` / `variant="mobile"`).

---
