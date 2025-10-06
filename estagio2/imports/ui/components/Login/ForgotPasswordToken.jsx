import React, { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { callApi } from '../../../api/callApi';
import { toast } from 'react-toastify';

export const ForgotPasswordToken = () => {
  const navigate = useNavigate();
  const { token } = useParams();
  const [password, setPassword] = useState(''); // Estado para la nueva contraseña
  const [confirmPassword, setConfirmPassword] = useState(''); // Estado para confirmar la nueva contraseña
  const [message, setMessage] = useState(null); // Estado para mostrar mensajes de éxito o error

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (password !== confirmPassword) {
      toast.error('Las contraseñas no coinciden', {
        position: 'top-center'
      });
      return;
    }

    try {
      // Preparar los parámetros para la API
      const credentials = {
        token: token, // El token de la URL
        newPassword: password // La nueva contraseña
      };

      // Llamar a la función `callApi` con los parámetros adecuados
      const response = await callApi('reset-password/change', credentials, undefined);
      if (response.code === '200') {
        toast.success(`${response.message}`, {
          position: 'top-center'
        });

        // Esperar 3 segundos antes de redirigir
        setTimeout(() => {
          navigate('/login');
        }, 3000); // 3000 milisegundos = 3 segundos
      } else {
        toast.error(`${response.message}`, {
          position: 'top-center'
        });
      }
    } catch (error) {
      toast.error('Error al intentar restablecer la contraseña', {
        position: 'top-center'
      });
    }
  };

  return (
    <div className="app">
      <div className="login">
        <div className="loginBox">
          <img src="https://www.intranek.com/images/general/logo.png" alt="Logo" />
          <h1>RESTABLECER CONTRASEÑA</h1>
          <div className="login-form">
            <form onSubmit={handleSubmit}>
              <div>
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  placeholder="CONTRASEÑA NUEVA"
                />
              </div>

              <div>
                <input
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  required
                  placeholder="CONFIRMAR CONTRASEÑA"
                />
              </div>

              <button className="boton" type="submit">
                RESTABLECER CONTRASEÑA
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  );
};
