import React, { useState } from 'react';
import Validator from 'validator';
import { version } from '/package.json';
import { useNavigate } from 'react-router-dom';
import { toast } from 'react-toastify';
import AuthService from '../../../service/authService';
import { Loading } from '../Loading';
import { usePermissions } from '../../../context/permissionsContext';
import { Eye, EyeOff } from 'lucide-react';

export const LoginForm = () => {
  const navigate = useNavigate();
  const [credentials, setCredentials] = useState({
    username: '',
    password: '',
    _remember_me: false
  });
  const [loading, setLoading] = useState(false);
  const [loadingSession, setLoadingSession] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [message, setMessage] = useState('');
  const { fetchPermissions } = usePermissions();

  const checkUsername = () => {
    if (credentials.username) {
      if (!Validator.isEmail(credentials.username)) {
        document.getElementById('email').focus();
        $('#alert-confirm').removeClass('d-none alert-success');
        $('#alert-confirm').addClass('show');
        return;
      } // Check email
      else {
        $('#alert-confirm').removeClass('show alert-success');
        $('#alert-confirm').addClass('d-none ');
      }
    } else {
      $('#alert-confirm').removeClass('show alert-success');
      $('#alert-confirm').addClass('d-none ');
      return;
    }
  };
  //Realizamos el submit del formulario
  const handleLogin = async () => {
    try {
      setLoading(true);
      const r = await AuthService.login(credentials);
      if (r.status === 200) {
        if (r.data.firstTime) {
          setMessage('');
          navigate('/change-password');
        } else {
          const req = await fetchPermissions();
          if (req.code === 200) {
            toast.success('Inicio de sesión exitoso', {
              position: 'top-center'
            });
            setMessage('');
            navigate('/');
          } else {
            toast.error(`${req.message}`, {
              position: 'top-center'
            });
            setMessage(req.message);
          }
        }
      }
    } catch (e) {
      setMessage(e.response.data.message ?? 'Credenciales incorrectas');
      toast.error(`${e.message}`, {
        position: 'top-center'
      });
    } finally {
      setLoading(false);
    }
  };

  if (loadingSession) {
    return <Loading text="Comprobando sesión" />;
  }

  return (
    <div className="app">
      <div className="login">
        <div className="loginBox">
          <img src="images/general/logo.png" alt="Logo" />
          <h1>INICIAR SESIÓN</h1>
          <form>
            <div className="login-form">
              <input
                type="email"
                id="email"
                placeholder="E-MAIL"
                className="w-full h-10"
                name="username"
                required
                onBlur={checkUsername}
                onChange={(e) => setCredentials({ ...credentials, username: e.target.value })}
              />
              <div className="flex w-full gap-2 items-center justify-center">
                <input
                  type={showPassword ? 'text' : 'password'}
                  placeholder="CONTRASEÑA"
                  name="password"
                  className="w-full h-10"
                  required
                  onChange={(e) => setCredentials({ ...credentials, password: e.target.value })}
                />
                <button
                  type="button"
                  className="w-10 h-10 mt-2 bg-white border-none rounded-md flex items-center justify-center"
                  onClick={() => setShowPassword(!showPassword)}
                >
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
              <div className="checkbox">
                <label className="content-input">
                  <input
                    type="checkbox"
                    name="_remember_me"
                    checked={credentials._remember_me}
                    onChange={(e) =>
                      setCredentials({
                        ...credentials,
                        _remember_me: e.target.checked
                      })
                    }
                  />{' '}
                  Recuérdame
                  <i></i>
                </label>
              </div>
              <div>{message ? <span className="textRed text-sm">{message}</span> : null}</div>
              {loading ? (
                <button className="boton" disabled>
                  Cargando...
                </button>
              ) : (
                <button className="boton" onClick={handleLogin}>
                  ENTRAR
                </button>
              )}
              <br />
              <button
                id="forgot_pass"
                onClick={() => navigate('/forgot-password')}
                className="font-weight-bold"
                style={{ border: 'none' }}
              >
                Olvidé mi contraseña
              </button>
            </div>
          </form>
          <p id="registro">{version}</p>
        </div>
      </div>
    </div>
  );
};
