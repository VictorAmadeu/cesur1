// imports/ui/components/Header/Header.jsx
//
// Header único (Desktop + Móvil)
//
// Objetivos (producción):
// - Unificar comportamiento entre master y Develop-Mobile.
// - Mantener UI estable: logo fijo + bloque derecho (LogoHeader).
// - Conservar mejora de master: cargar perfil para employeeName/companyName.
// - Evitar errores de tooling (ESLint/TS en VSCode) sin tocar LogoHeader.jsx.
//
// Nota (guía Etapa 3):
// - Header debe ser único y vivir en los Layouts (no duplicarlo en Nav). :contentReference[oaicite:1]{index=1}

import React, { useEffect, useState } from "react";
import { LogoHeader } from "./LogoHeader";

import CompanyService from "../../../service/companyService";
import AuthService from "../../../service/authService";
import UserService from "../../../service/userService";

/**
 * buildUserSummary
 * ----------------
 * Normaliza la respuesta del perfil y devuelve:
 *  - employeeName: nombre completo del empleado (si existe)
 *  - companyName: nombre de la compañía (si existe)
 *
 * Importante:
 * - Función defensiva: si faltan campos, devuelve strings vacíos.
 * - No cambia contratos ni “inventa” datos: solo normaliza y compone.
 */
const buildUserSummary = (profilePayload, companyPayload) => {
  // A veces el perfil puede venir como array en data[0] o como objeto en data
  const normalizedData = Array.isArray(profilePayload?.data)
    ? profilePayload.data[0]
    : profilePayload?.data || {};

  // Nombre completo (solo con piezas existentes)
  const pieces = [
    normalizedData.name || normalizedData.firstname,
    normalizedData.lastname1,
    normalizedData.lastname2,
  ].filter(Boolean);

  const employeeName = (pieces.length > 0 ? pieces.join(" ") : "").trim();

  // Empresa desde distintos campos posibles del perfil
  const userCompany =
    normalizedData.company?.name ||
    normalizedData.company_name ||
    normalizedData.companyName ||
    "";

  return {
    employeeName,
    // Si no viene empresa en perfil, intentamos fallback a lo que venga con el logo
    companyName: (userCompany || companyPayload?.company_name || "").trim(),
  };
};

export const Header = () => {
  const [loading, setLoading] = useState(true);

  // Logo y rol
  const [logo, setLogo] = useState("");
  const [role, setRole] = useState("ROLE_USER");

  // Nombre empleado + empresa (como en master)
  const [userSummary, setUserSummary] = useState({
    employeeName: "",
    companyName: "",
  });

  /**
   * LogoHeaderAny
   *
   * En este repo, VSCode/TS puede inferir props de LogoHeader de forma inconsistente
   * (a veces exige employeeName/companyName y otras veces dice que “no existen”).
   * Para NO tocar LogoHeader.jsx y NO romper producción:
   * - lo casteamos a `any` SOLO a nivel de editor/tooling.
   * - el runtime no cambia.
   */
  const LogoHeaderAny = /** @type {any} */ (LogoHeader);

  /**
   * loadHeaderData
   * --------------
   * Recupera en paralelo:
   * - Logo / datos de compañía
   * - Perfil de usuario
   * - Rol del usuario
   *
   * Si algo falla:
   * - No rompemos UI: dejamos valores seguros.
   */
  const loadHeaderData = async () => {
    try {
      setLoading(true);

      // Logo + perfil en paralelo (como tu master)
      const [logoResponse, profileResponse] = await Promise.all([
        CompanyService.getLogo(),
        UserService.profile(),
      ]);

      setLogo(logoResponse ?? "");

      // Rol (compatible con getRole como función o valor)
      const roleValue =
        typeof AuthService.getRole === "function" ? AuthService.getRole() : AuthService.getRole;

      setRole(roleValue ?? "ROLE_USER");

      // Nombre y empresa (defensivo)
      setUserSummary(buildUserSummary(profileResponse, logoResponse));
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error("Error al cargar los datos del header", error);

      // Valores seguros (no UI rota)
      setLogo("");
      setRole("ROLE_USER");
      setUserSummary({ employeeName: "", companyName: "" });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadHeaderData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Placeholder para reservar altura y evitar “saltos” de layout
  if (loading) {
    return (
      <header className="headerMain">
        <div className="header h-[65px]" />
      </header>
    );
  }

  return (
    <header className="headerMain">
      <div className="header">
        {/* Logo fijo de Intranek */}
        <img className="logo" src="/images/general/logo.png" alt="Intranek" />

        {/* Bloque derecho (LogoHeader)
            - En master ya usabas employeeName/companyName (si existen).
            - En móvil, si LogoHeader no los usa, simplemente los ignora.
            - El cast a Any evita errores “fantasma” de TS en VSCode. */}
        <LogoHeaderAny
          logo={logo}
          role={role}
          employeeName={userSummary.employeeName}
          companyName={userSummary.companyName}
        />
      </div>
    </header>
  );
};
