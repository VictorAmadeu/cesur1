// imports/ui/routes/index.jsx
//
// Paso 2.4 (Etapa 3):
// - Asegurar que ResponsiveLayout (Layout/index.jsx) envuelve correctamente las rutas.
// - NO es necesario cambiar rutas; solo unificar y validar el wrapper.
//
// Nota de producción:
// - Mantener este archivo idéntico en victor/unir-ramas-desktop y victor/unir-ramas-mobile
//   para evitar divergencias entre ramas.

import React from "react";
import { BrowserRouter, Routes, Route } from "react-router-dom";

// Páginas
import { Home } from "../pages/Home";
import { Login } from "../pages/Login";
import { NotFound } from "../pages/error/notFound";
import { DocumentosPage } from "../pages/Documentos";
import { RegistrarTiempoPage } from "../pages/RegistrarTiempo";
import { ProfilePage } from "../pages/Profile";
import { VerTiemposPage } from "../pages/VerTiempos";
import { AusenciaPage } from "../pages/Ausencias";
import { HorarioPage } from "../pages/Horario";
import { ForgotPasswordPage } from "../pages/ForgotPassword";
import { ForgotPasswordTokenPage } from "../pages/ForgotPasswordToken";
import { ChangePasswordFirstTimePage } from "../pages/ChangePasswordFirstTimePage";
import { JustificationPage } from "../pages/Justification";

// Layout responsivo (decide DesktopLayout vs MovilLayout por viewport)
import ResponsiveLayout from "../components/Layout";

export const RoutesApp = () => {
  return (
    <BrowserRouter>
      <Routes>
        {/* =========================================================
            Rutas dentro del layout responsivo (usuarios autenticados)
            =========================================================
            IMPORTANTE:
            - Este <Route element={<ResponsiveLayout />} /> actúa como "wrapper".
            - Las rutas hijas se renderizan dentro de <Outlet /> del layout.
        */}
        <Route element={<ResponsiveLayout />}>
          <Route path="/" element={<Home />} />

          {/* Tiempos */}
          <Route path="/registrar-tiempo" element={<RegistrarTiempoPage />} />
          <Route path="/ver-tiempo" element={<VerTiemposPage />} />
          <Route path="/justification" element={<JustificationPage />} />

          {/* Empleado */}
          <Route path="/documentos" element={<DocumentosPage />} />
          <Route path="/ausencia" element={<AusenciaPage />} />
          <Route path="/horario" element={<HorarioPage />} />

          {/* Cuenta */}
          <Route path="/perfil" element={<ProfilePage />} />
        </Route>

        {/* =========================================================
            Rutas fuera del layout (usuarios NO autenticados)
            ========================================================= */}
        <Route path="/change-password" element={<ChangePasswordFirstTimePage />} />
        <Route path="/login" element={<Login />} />
        <Route path="/forgot-password" element={<ForgotPasswordPage />} />
        <Route path="/forgot-password/:token" element={<ForgotPasswordTokenPage />} />

        {/* 404 */}
        <Route path="*" element={<NotFound />} />
      </Routes>
    </BrowserRouter>
  );
};