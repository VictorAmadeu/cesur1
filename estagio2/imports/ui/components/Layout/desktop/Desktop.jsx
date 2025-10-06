import React from 'react';
import NavDesktop from './NavDesktop';
import { ToastContainer } from 'react-toastify';

const DesktopLayout = ({ children }) => {
  return (
    <div>
      <ToastContainer />
      <NavDesktop />
      <div className="w-full h-full">{children}</div>
    </div>
  );
};

export default DesktopLayout;
