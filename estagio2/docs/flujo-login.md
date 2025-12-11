# Flujo de login y sesión (Intranek)

> Objetivo: dejar por escrito qué hace exactamente el front-end de Intranek cuando un usuario inicia sesión, mantiene la sesión viva y cierra sesión. Esta información sirve de base para los tests unitarios de `AuthService` y de la pantalla de Login.

---

## 1. Entrada y validaciones

**Punto de entrada (UI)**  
- Componente principal: `imports/ui/components/Login/LoginForm.jsx`.
- El formulario recoge: `email`, `password` y el checkbox `_remember_me`.

**Validaciones en el front-end**

- El email se valida con la librería `validator` (formato correcto de correo).
- Se permite mostrar/ocultar la contraseña desde la propia UI.
- En la versión web, cuando `_remember_me` es verdadero se guarda el email en `localStorage` para precargarlo en futuros logins.
- No se permite enviar el formulario si faltan campos obligatorios o el email es inválido.

---

## 2. Servicios y efectos secundarios

**Servicio principal**

- `AuthService.login` llama al endpoint `/login_check`.
- En caso de autenticación correcta:
  - Guarda las cookies `tokenIntranEK` y `name`.
  - La expiración de las cookies depende de `_remember_me`:
    - **30 días** si `_remember_me` es verdadero.
    - **8 horas** si `_remember_me` es falso.

**Permisos y rol**

- Tras el login, `AuthService.fetchPermissions` obtiene permisos y rol del usuario.
- `PermissionsContext` almacena en cookies y en estado global:
  - `permissions`
  - `role`
- Esta información se usa para controlar qué secciones de la aplicación están visibles o activas.

**KeepAlive de sesión**

- `AuthService.isAuthenticated`:
  - Llama a `/global/keepAlive`.
  - Si la sesión sigue siendo válida, permite que el usuario continúe navegando.
  - Si la sesión ha caducado, redirige a `/login` y fuerza un nuevo inicio de sesión.

**Logout**

- `AuthService.logout`:
  - Borra las cookies de sesión (`tokenIntranEK`, `name`, `permissions`, `role`).
  - Navega a `/login`.

---

## 3. Errores gestionados

- Errores de credenciales incorrectas:
  - El servidor devuelve un error y `LoginForm` muestra un mensaje adecuado en la UI.
- Errores de red o del servidor:
  - Se notifican también desde la UI, sin dejar al usuario en un estado inconsistente.
- Sesión caducada:
  - `isAuthenticated` detecta el problema y redirige de nuevo a `/login`, evitando pantallas en blanco.

---

## 4. Cobertura prevista con tests unitarios

Los tests unitarios para este flujo deberán comprobar, como mínimo:

1. Que `AuthService.login` guarda las cookies correctas y respeta la expiración de 30 días / 8 horas según `_remember_me`.
2. Que `AuthService.fetchPermissions` almacena correctamente `permissions` y `role` en cookies y en `PermissionsContext`.
3. Que `AuthService.isAuthenticated`:
   - Mantiene la sesión cuando `/global/keepAlive` responde correctamente.
   - Fuerza logout y redirección a `/login` cuando la sesión no es válida.
4. Que `AuthService.logout` borra todas las cookies relacionadas con la sesión y navega a `/login`.
