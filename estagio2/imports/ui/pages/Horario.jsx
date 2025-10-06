import React, { useEffect, useState } from "react";
import { Loading } from "../components/Loading";
import AuthService from "/imports/service/authService";
import { useNavigate } from "react-router-dom";
import Horario from "../components/Horario";

export const HorarioPage = () => {
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
      <h2 className="text-2xl font-semibold text-center text-gray-700 mt-4">
        Mi Horario
      </h2>
      <Horario />
    </div>
  );
};
