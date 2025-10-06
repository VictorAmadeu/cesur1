import React, { useEffect, useState } from "react";
import { useMediaQuery } from 'react-responsive';
import DesktopLayout from "./desktop/Desktop";
import MovilLayout from "./movil/Movil";
import { Outlet } from "react-router-dom";

export default function ResponsiveLayout() {
  const isMobile = useMediaQuery({ query: '(max-width: 1024px)' });

  return isMobile ? (
    <MovilLayout>
      <Outlet />
    </MovilLayout>
  ) : (
    <DesktopLayout>
      <Outlet />
    </DesktopLayout>
  );
}
