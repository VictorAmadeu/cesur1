# Flujo de login y sesión: Reglas de negocio que deberán cubrir los tests unitarios.

1. **UI**: `imports/ui/components/Login/LoginForm.jsx` gestiona el formulario de autenticación.
2. **Validaciones**: se valida el formato del email con la librería `validator`, se permite mostrar/ocultar la contraseña y se recuerda el email en `localStorage` en la versión web mediante el checkbox `_remember_me`.
3. **Servicio**: `AuthService.login` llama a `/login_check` y, si la autenticación es correcta, guarda `tokenIntranEK` y `name` en cookies con una expiración de:
   - 30 días cuando `_remember_me` es verdadero.
   - 8 horas cuando `_remember_me` es falso.
4. **Permisos/Rol**: `AuthService.fetchPermissions` y `PermissionsContext` almacenan en cookies (`permissions`, `role`) y en el estado global los permisos y el rol del usuario.
5. **KeepAlive**: `AuthService.isAuthenticated` utiliza `/global/keepAlive` para comprobar si la sesión sigue siendo válida; si no, redirige a `/login`.
6. **Logout**: `AuthService.logout` borra las cookies y navega a `/login`.

Las pruebas unitarias cubren expiraciones de cookies, manejo de errores y almacenamiento correcto de tokens y permisos.
