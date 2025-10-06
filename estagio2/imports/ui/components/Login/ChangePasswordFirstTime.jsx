import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'react-toastify';
import AuthService from '/imports/service/authService';

export const ChangePasswordFirstTime = () => {
  const navigate = useNavigate();
  const [password, setPassword] = useState(''); // Estado para la nueva contraseña
  const [confirmPassword, setConfirmPassword] = useState(''); // Estado para confirmar la nueva contraseña

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
        newPassword: password
      };

      const req = await AuthService.changePasswordFirstTime(credentials);
      if (req.code === '200') {
        toast.success('Contraseña cambiada con exito', {
          position: 'top-center'
        });
        navigate('/');
      } else {
        toast.error(req.message ?? 'Error cambiando la contraseña', {
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
