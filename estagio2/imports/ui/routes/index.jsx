<<<<<<< HEAD
import React from "react";
import { BrowserRouter, Routes, Route } from "react-router-dom";
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
import { CheckRegistersPage } from "../pages/CheckRegisters";
import ResponsiveLayout from "../components/Layout";
=======
import React from 'react';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { Home } from '../pages/Home';
import { Login } from '../pages/Login';
import { NotFound } from '../pages/error/notFound';
import { DocumentosPage } from '../pages/Documentos';
import { RegistrarTiempoPage } from '../pages/RegistrarTiempo';
import { ProfilePage } from '../pages/Profile';
import { VerTiemposPage } from '../pages/VerTiempos';
import { AusenciaPage } from '../pages/Ausencias';
import { HorarioPage } from '../pages/Horario';
import { ForgotPasswordPage } from '../pages/ForgotPassword';
import { ForgotPasswordTokenPage } from '../pages/ForgotPasswordToken';
import { ChangePasswordFirstTimePage } from '../pages/ChangePasswordFirstTimePage';
import ResponsiveLayout from '../components/Layout';
import { JustificationPage } from '../pages/Justification';
>>>>>>> jona

export const RoutesApp = () => {
  return (
    <BrowserRouter>
      <Routes>
        {/* Rutas accesibles para usuarios autenticados */}
        <Route element={<ResponsiveLayout />}>
          <Route path="/" element={<Home />} />
          <Route path="/registrar-tiempo" element={<RegistrarTiempoPage />} />
          <Route path="/ver-tiempo" element={<VerTiemposPage />} />
          <Route path="/ausencia" element={<AusenciaPage />} />
          <Route path="/horario" element={<HorarioPage />} />
          <Route path="/perfil" element={<ProfilePage />} />
          <Route path="/documentos" element={<DocumentosPage />} />
<<<<<<< HEAD
          <Route path="/justificar" element={<CheckRegistersPage />} />
=======
          <Route path="/justification" element={<JustificationPage />} />
>>>>>>> jona
        </Route>

        {/* Rutas accesibles para usuarios no autenticados */}
        <Route path="/change-password" element={<ChangePasswordFirstTimePage />} />
        <Route path="/login" element={<Login />} />

        <Route path="/forgot-password" element={<ForgotPasswordPage />} />

        <Route path="/forgot-password/:token" element={<ForgotPasswordTokenPage />} />

        {/* Ruta de error 404 */}
        <Route path="*" element={<NotFound />} />
      </Routes>
    </BrowserRouter>
  );
};
