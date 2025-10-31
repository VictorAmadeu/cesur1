import React, { useState, useEffect } from 'react';
import Validator from 'validator';
import { version } from '/package.json';
import { useNavigate } from 'react-router-dom';
import { toast } from 'react-toastify';
import AuthService from '../../../service/authService';
import { Loading } from '../Loading';
import { usePermissions } from '../../../context/permissionsContext';
import { Eye, EyeOff } from 'lucide-react';

/**
 * LoginForm
 * -------------------------------------------------------------------
 * Objetivo de la Tarea 47:
 *  - Recordar el ÚLTIMO email usado y sugerirlo/autocompletar al escribir.
 *  - Ofrecer opción visible para activar/desactivar (checkbox "Recordar mi email").
 *  - Botón para olvidar manualmente el email guardado.
 *
 * Implementación:
 *  - Guardamos/recuperamos el email en localStorage bajo la clave EMAIL_KEY.
 *  - Al montar el componente, si existe un email guardado, lo precargamos
 *    y dejamos marcada la casilla "Recordar mi email".
 *  - Añadimos un <datalist> con el email guardado para que el navegador
 *    sugiera/autocompletar cuando el usuario empiece a escribir.
 *  - En el login exitoso, persistimos o eliminamos el email según el checkbox.
 *
 * Notas de seguridad/producción:
 *  - Solo persistimos el email (no la contraseña).
 *  - No introducimos dependencias nuevas ni rompemos el flujo existente.
 *  - Evitamos enviar el formulario por defecto (preventDefault) para no recargar.
 */

const EMAIL_KEY = 'emailRecordado'; // clave única para esta funcionalidad

export const LoginForm = () => {
  const navigate = useNavigate();
  const { fetchPermissions } = usePermissions();

  // Estado controlado de credenciales y flags de UI
  const [credentials, setCredentials] = useState({
    username: '',
    password: '',
    _remember_me: false, // se usa también por backend para "recordar sesión" si procede
  });
  const [loading, setLoading] = useState(false);
  const [loadingSession, setLoadingSession] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [message, setMessage] = useState('');

  /**
   * Al montar: si hay email guardado en localStorage, lo precargamos
   * y marcamos por defecto el checkbox de "Recordar mi email".
   */
  useEffect(() => {
    try {
      const saved = localStorage.getItem(EMAIL_KEY);
      if (saved) {
        setCredentials((prev) => ({
          ...prev,
          username: saved,
          _remember_me: true,
        }));
      }
    } catch {
      // Si el navegador bloquea localStorage (modo privado estricto), simplemente ignoramos.
    }
  }, []);

  /**
   * Validación básica del email en onBlur.
   * - No usamos jQuery ni tocamos elementos DOM inexistentes.
   * - Solo mostramos un aviso si el formato no es válido.
   */
  const checkUsername = () => {
    if (!credentials.username) return;
    if (!Validator.isEmail(credentials.username)) {
      setMessage('El formato del email no es válido.');
      toast.warn('Introduce un email válido (ej: usuario@dominio.com)', {
        position: 'top-center',
      });
      // Devolvemos el foco al campo email para comodidad del usuario.
      const el = document.getElementById('email');
      if (el) el.focus();
    } else {
      // Limpiamos mensajes si ya es válido
      setMessage('');
    }
  };

  /**
   * Opción rápida para "olvidar" el email guardado.
   * Limpia localStorage y el estado del input/checkbox.
   */
  const olvidarEmail = () => {
    try {
      localStorage.removeItem(EMAIL_KEY);
    } catch {}
    setCredentials((prev) => ({
      ...prev,
      username: '',
      _remember_me: false,
    }));
    toast.info('Email guardado eliminado.', { position: 'top-center' });
  };

  /**
   * Envío de formulario (evitamos submit por defecto).
   * - Llama al servicio de autenticación.
   * - Si el login es correcto:
   *   - Guarda o elimina el email según el checkbox.
   *   - Gestiona primer inicio de contraseña o navegación normal.
   */
  const handleLogin = async () => {
    try {
      setLoading(true);

      const r = await AuthService.login(credentials);
      if (r.status === 200) {
        // Persistimos o eliminamos el email guardado según preferencia
        try {
          if (credentials._remember_me && credentials.username) {
            localStorage.setItem(EMAIL_KEY, credentials.username);
          } else {
            localStorage.removeItem(EMAIL_KEY);
          }
        } catch {
          // Si no podemos escribir en localStorage, seguimos sin romper el flujo
        }

        if (r.data?.firstTime) {
          // Primer login: redirigimos al cambio de contraseña obligatorio
          setMessage('');
          navigate('/change-password');
          return;
        }

        // Cargamos permisos antes de entrar a la app
        const req = await fetchPermissions();
        if (req.code === 200) {
          toast.success('Inicio de sesión exitoso', { position: 'top-center' });
          setMessage('');
          navigate('/');
        } else {
          toast.error(`${req.message}`, { position: 'top-center' });
          setMessage(req.message ?? 'No se pudieron obtener los permisos.');
        }
      }
    } catch (e) {
      // Mensaje de backend si existe; si no, uno genérico
      const apiMsg =
        e?.response?.data?.message ??
        e?.message ??
        'Credenciales incorrectas';
      setMessage(apiMsg);
      toast.error(apiMsg, { position: 'top-center' });
    } finally {
      setLoading(false);
    }
  };

  /**
   * Maneja "Enter" sobre el formulario sin recargar la página.
   */
  const onSubmitLogin = (ev) => {
    ev.preventDefault();
    if (!credentials.username || !Validator.isEmail(credentials.username)) {
      checkUsername();
      return;
    }
    if (!credentials.password) {
      setMessage('Introduce tu contraseña.');
      return;
    }
    handleLogin();
  };

  // Si estamos comprobando sesión, mostramos el loader como antes
  if (loadingSession) {
    return <Loading text="Comprobando sesión" />;
  }

  // Email guardado para <datalist> (sugerencia/autocompletado nativo del navegador)
  let emailGuardado = '';
  try {
    emailGuardado = localStorage.getItem(EMAIL_KEY) || '';
  } catch {
    emailGuardado = '';
  }

  return (
    <div className="app">
      <div className="login">
        <div className="loginBox">
          <img src="images/general/logo.png" alt="Logo" />
          <h1>INICIAR SESIÓN</h1>

          {/* Usamos onSubmit para capturar Enter y evitar recarga */}
          <form onSubmit={onSubmitLogin}>
            <div className="login-form">
              {/* Campo E-MAIL con datalist para sugerir el último email guardado */}
              <input
                type="email"
                id="email"
                name="username"
                placeholder="E-MAIL"
                className="w-full h-10"
                autoComplete="email"
                required
                list="hint-emails"
                value={credentials.username}
                onBlur={checkUsername}
                onChange={(e) =>
                  setCredentials({ ...credentials, username: e.target.value })
                }
              />
              {/* Sugerencia/autocompletado: si hay email guardado, el navegador lo propondrá al escribir */}
              <datalist id="hint-emails">
                {emailGuardado ? <option value={emailGuardado} /> : null}
              </datalist>

              {/* Campo contraseña con botón para mostrar/ocultar */}
              <div className="flex w-full gap-2 items-center justify-center">
                <input
                  type={showPassword ? 'text' : 'password'}
                  placeholder="CONTRASEÑA"
                  name="password"
                  className="w-full h-10"
                  autoComplete="current-password"
                  required
                  onChange={(e) =>
                    setCredentials({ ...credentials, password: e.target.value })
                  }
                />
                <button
                  type="button"
                  className="w-10 h-10 mt-2 bg-white border-none rounded-md flex items-center justify-center"
                  aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                  onClick={() => setShowPassword((v) => !v)}
                >
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>

              {/* Opción visible para activar/desactivar el recordatorio de email */}
              <div className="checkbox">
                <label className="content-input">
                  <input
                    type="checkbox"
                    name="_remember_me"
                    checked={credentials._remember_me}
                    onChange={(e) =>
                      setCredentials({
                        ...credentials,
                        _remember_me: e.target.checked,
                      })
                    }
                  />{' '}
                  Recordar mi email
                  <i></i>
                </label>
              </div>

              {/* Enlace para borrar rápidamente el email guardado (solo si existe) */}
              {emailGuardado && (
                <button
                  type="button"
                  className="text-blue-500 underline text-sm mt-1"
                  onClick={olvidarEmail}
                  style={{ border: 'none', background: 'transparent' }}
                >
                  Olvidar email guardado
                </button>
              )}

              {/* Mensajes de error/estado */}
              <div>
                {message ? <span className="textRed text-sm">{message}</span> : null}
              </div>

              {/* Botón de entrar:
                  - type="submit" para que funcione Enter en el formulario
                  - deshabilitado mientras loading */}
              {loading ? (
                <button className="boton" disabled>
                  Cargando...
                </button>
              ) : (
                <button className="boton" type="submit">
                  ENTRAR
                </button>
              )}

              <br />

              {/* Flujo de "Olvidé mi contraseña" (sin cambios funcionales) */}
              <button
                id="forgot_pass"
                type="button"
                onClick={() => navigate('/forgot-password')}
                className="font-weight-bold"
                style={{ border: 'none' }}
              >
                Olvidé mi contraseña
              </button>
            </div>
          </form>

          {/* Versión del front para soporte */}
          <p id="registro">{version}</p>
        </div>
      </div>
    </div>
  );
};
