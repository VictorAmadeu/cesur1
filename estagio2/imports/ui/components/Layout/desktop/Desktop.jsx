import React from 'react';
import NavDesktop from './NavDesktop';
import { ToastContainer } from 'react-toastify';

const DesktopLayout = ({ children }) => {
  return (
    <div className="h-screen flex flex-col">
      <NavDesktop />
      <div className="flex-1 w-full">{children}</div>
      <ToastContainer />
    </div>
  );
};

export default DesktopLayout;
