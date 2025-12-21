import React from 'react';
import { useMediaQuery } from 'react-responsive';
import DesktopLayout from './desktop/Desktop.jsx';
import MovilLayout from './movil/Movil.jsx';
import { Outlet } from 'react-router-dom';

export default function ResponsiveLayout() {
  const isMobile = useMediaQuery({ query: '(max-width: 1024px)' });

  if (isMobile) {
    return (
      <MovilLayout>
        <Outlet />
      </MovilLayout>
    );
  }

  return (
    <DesktopLayout>
      <Outlet />
    </DesktopLayout>
  );
}