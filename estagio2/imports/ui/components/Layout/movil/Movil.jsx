import React from 'react';
import { Header } from '../../Header/Header';
import NavMovil from './NavMovil';
import { ToastContainer } from 'react-toastify';

export default function MovilLayout({ children }) {
  return (
    <div>
      <ToastContainer />
      <Header />
      {children}
      <NavMovil />
    </div>
  );
}
