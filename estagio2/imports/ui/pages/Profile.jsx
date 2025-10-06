import React, { useEffect, useState } from 'react';
import { ProfileInfo } from '../components/Perfil/ProfileInfo';
import { Loading } from '../components/Loading';
import AuthService from '/imports/service/authService';
import { useNavigate } from 'react-router-dom';

export const ProfilePage = () => {
  const navigate = useNavigate();
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    keepAliveCheck();
  }, []);

  const keepAliveCheck = async () => {
    const r = await AuthService.isAuthenticated();
    if (r.code === '200') {
      if (r.key === 'FIRST_TIME') {
        navigate("/change-password");
        setIsLoading(false);
      } else {
        setIsLoading(false);
      }
    } else {
      navigate("/login");
      setIsLoading(false);
      return;
    }
  };

  if (isLoading) {
    return <Loading text="Comprobando sesión" />;
  }

  return (
    <div>
      <h2 className="text-2xl font-semibold text-center text-gray-700 mt-4">Mi cuenta</h2>
      <ProfileInfo />
    </div>
  );
};