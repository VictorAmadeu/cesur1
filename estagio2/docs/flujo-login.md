# Flujo de login y sesión (Intranek)

> **Objetivo:** dejar por escrito qué hace exactamente el front-end de Intranek cuando un usuario inicia sesión, mantiene la sesión viva y cierra sesión.  
> Esta información sirve de base para los tests unitarios de `AuthService` y de la pantalla de Login.

---

## 1. Entrada y validaciones (UI)

### 1.1 Punto de entrada

- Componente principal: `imports/ui/components/Login/LoginForm.jsx`.
- El formulario recoge:
  - `username` (email)
  - `password`
  - checkbox `_remember_me`

> Nota: `_remember_me` se usa con doble propósito:
> - **UX:** recordar el email (solo el email) para precargarlo en futuros logins.
> - **Sesión:** se envía también al backend (si aplica) para “recordar sesión”/duración de cookies según la política existente.

---

### 1.2 Validaciones en el front-end (sin jQuery)

- **No se usa jQuery**: toda la validación y comportamiento se resuelve con React (estado controlado + handlers).
- El email se valida con la librería `validator`:
  - Validación en `onBlur` (aviso si el formato no es válido).
  - En el envío (`onSubmit`) se bloquea el login si:
    - Falta el email o no tiene formato válido.
    - Falta la contraseña.
- Se permite **mostrar/ocultar la contraseña** desde la UI (botón con icono).
- El formulario evita recargas:
  - `onSubmit` hace `preventDefault()` para no recargar la SPA.

---

## 2. Persistencia del email (“Recordar mi email”)

### 2.1 Al montar el formulario

- Se intenta leer el último email guardado en `localStorage` (clave interna del componente, por ejemplo `EMAIL_KEY`).
- Si existe:
  - Se precarga en el input de email.
  - Se marca el checkbox `_remember_me`.

### 2.2 Durante el login exitoso

- Si el login es correcto:
  - Si `_remember_me` está activo y hay `username`:
    - Se guarda el email en `localStorage`.
  - Si `_remember_me` está desactivado:
    - Se elimina el email guardado de `localStorage`.

### 2.3 “Olvidar email guardado”

- La UI expone una acción para eliminar manualmente el email guardado:
  - Borra el valor de `localStorage`.
  - Limpia el estado del formulario y desmarca `_remember_me`.

> Importante (producción): **solo se persiste el email**, nunca la contraseña.

---

## 3. Servicios implicados y efectos secundarios

### 3.1 Servicio principal de autenticación

- `AuthService.login(credentials)` llama al endpoint `/login_check`.
- En caso de autenticación correcta (HTTP 200):
  - Se establecen cookies de sesión (por ejemplo `tokenIntranEK` y `name`) según la implementación existente.
  - La política de duración/expiración de cookies depende de `_remember_me` y de la implementación actual (front/back).

> Nota: este documento describe el flujo del front. La duración exacta de cookies la fija la implementación existente (y debe verificarse con los tests de `AuthService`).

---

### 3.2 Permisos y rol

- Tras el login:
  - Se ejecuta `fetchPermissions()` desde `PermissionsContext`.
- `PermissionsContext` mantiene en estado global (y donde aplique según implementación):
  - `permissions`
  - `role`

Esta información controla:
- Qué secciones se ven.
- Qué acciones están habilitadas (por ejemplo, fichaje manual, proyectos, registro/verificación de dispositivo, etc.).

---

## 4. KeepAlive de sesión y navegación protegida

### 4.1 Verificación de sesión

- `AuthService.isAuthenticated()`:
  - Llama a `/global/keepAlive`.
  - Si la sesión sigue siendo válida:
    - Permite continuar navegando.
  - Si la sesión caduca o no es válida:
    - Redirige a `/login` para forzar un nuevo inicio de sesión.

---

## 5. Logout

- `AuthService.logout()`:
  - Borra cookies relacionadas con la sesión (por ejemplo: `tokenIntranEK`, `name`, `permissions`, `role`).
  - Navega a `/login`.

> Nota: el logout **no** tiene por qué borrar el email recordado.  
> El email recordado se gestiona por la opción “Recordar mi email” y el botón “Olvidar email guardado”.

---

## 6. Errores gestionados

- Credenciales incorrectas:
  - El backend devuelve error y `LoginForm` muestra un mensaje en UI (y toast).
- Error de red/servidor:
  - Se notifica en UI sin dejar al usuario en estado inconsistente.
- Sesión caducada:
  - `isAuthenticated()` detecta el problema y fuerza navegación a `/login`.

---

## 7. Cobertura prevista con tests unitarios

Los tests unitarios para este flujo deberían comprobar, como mínimo:

1. **Validación de UI**
   - No permite login si el email es inválido.
   - No permite login si la contraseña está vacía.

2. **Persistencia del email**
   - Si `_remember_me = true` y login exitoso:
     - Guarda email en `localStorage`.
   - Si `_remember_me = false` y login exitoso:
     - Elimina email de `localStorage`.
   - “Olvidar email guardado” elimina el email y limpia estado.

3. **Autenticación**
   - `AuthService.login` llama a `/login_check` y gestiona el resultado según contrato actual.
   - En caso de `firstTime = true` navega a `/change-password`.

4. **Permisos**
   - `fetchPermissions()` se ejecuta tras login y actualiza el contexto.

5. **KeepAlive**
   - `AuthService.isAuthenticated` mantiene sesión cuando `/global/keepAlive` es válido.
   - Redirige a `/login` cuando no es válido.

6. **Logout**
   - `AuthService.logout` elimina cookies de sesión y navega a `/login`.
