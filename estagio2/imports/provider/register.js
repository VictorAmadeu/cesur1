import React, { createContext, useState, useContext } from 'react';

// Creamos un contexto para nuestro estado booleano
const RegisterContext = createContext();

// Creamos un provider para nuestro contexto
export const RegisterProvider = ({ children }) => {
  const [isRegisterHome, setIsRegisterHome] = useState(false);
  const [isChange, setIsChange] = useState(false)

  const setRegisterStatus = (status) => {
    setIsRegisterHome(status);
  };

  return (
    <RegisterContext.Provider value={{ isRegisterHome, setRegisterStatus, isChange, setIsChange }}>
      {children}
    </RegisterContext.Provider>
  );
};

// Un hook personalizado para consumir el contexto Register
export const useRegister = () => useContext(RegisterContext);
