import React, { useEffect, useState } from 'react';
import Cookies from 'js-cookie';
import { useDate } from '../../../provider/date';
import AuthService from '/imports/service/authService';
import UserService from '/imports/service/userService';
import { useNavigate } from 'react-router-dom';
import CheckInDaily from '../Fichar/CheckInDaily';
import { toast } from 'react-toastify';

export const Bienvenida = () => {
  const [data, setData] = useState({ name: '' });
  const [loading, setLoading] = useState(true);
  const { setDate } = useDate();
  const navigate = useNavigate();

  const getInfo = async () => {
    try {
      setLoading(true);
      const name = Cookies.get('name');
      if (!name) {
        const checkSession = await AuthService.isAuthenticated();
        if (checkSession.code !== '200') {
          navigate('/login');
          return;
        }
        const response = await UserService.profile();

        if (response.data == 200) {
          setData({ name: response.data[0].name });
        } else {
          setData({ name: 'undefined' });
        }
      } else {
        setData({ name: name });
      }
    } catch (error) {
      console.log(error);
      toast.error('Error al obtener la información del usuario', { position: 'top-center' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    getInfo();
    setDate(new Date());
  }, []);

  return (
    <div>
      <main className="mainInformes">
        {loading ? (
          <p>Cargando...</p>
        ) : (
          <>
            <h1>¡Hola, {data?.name ?? ''}!</h1>
            <CheckInDaily home={false} />
          </>
        )}
      </main>
    </div>
  );
};
