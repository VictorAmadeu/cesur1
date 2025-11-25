import React, { useState } from 'react';
import { callApi } from '../../../api/callApi';
import { toast } from 'react-toastify';
import { useNavigate } from 'react-router-dom';

export const ForgotPassword = () => {
  const [email, setEmail] = useState('');
  const navigate = useNavigate();
  const handleSubmit = async (e) => {
    e.preventDefault();

    try {
      const response = await callApi('reset-password/request', { email: email }, undefined);
      if (response.code === '200') {
        toast.success(`${response.message}`, {
          position: 'top-center'
        });
      } else {
        toast.error(`${response.message}`, {
          position: 'top-center'
        });
      }
    } catch (err) {
      toast.error('Hubo un problema con la solicitud. Inténtalo de nuevo.', {
        position: 'top-center'
      });
    }
  };

  return (
    <div className="app">
      <div className="login">
        <div className="loginBox">
          <img src="https://www.intranek.com/images/general/logo.png" alt="Logo" />
          <h1>RECUPERAR CONTRASEÑA</h1>
          <div className="login-form">
            <form onSubmit={handleSubmit}>
              <input
                type="email"
                id="email"
                placeholder="E-MAIL"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
              <button className="boton" type="submit">
                ENVIAR
              </button>
              <button
                id="forgot_pass"
                type="button"
                onClick={() => navigate('/login')}
                className="font-weight-bold"
                style={{ border: 'none' }}
              >
                Volver
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  );
};
