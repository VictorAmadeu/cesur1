import React from 'react';
import { ToastContainer } from 'react-toastify';

import { Header } from '../../Header/Header';
import NavMovil from './NavMovil';

export default function MovilLayout({ children }) {
  return (
    <div className="min-h-screen flex flex-col">
      <Header />
      <div className="flex-1 w-full">{children}</div>
      <NavMovil />
      <ToastContainer />
    </div>
  );
}
