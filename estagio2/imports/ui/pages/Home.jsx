import React, { useEffect, useState } from "react";
import { HomeIndex } from "../components/Home/Home";
import { useNavigate } from "react-router-dom";
import { Loading } from "../components/Loading";
import AuthService from "/imports/service/authService";

export const Home = () => {
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

  return <HomeIndex />;
};
